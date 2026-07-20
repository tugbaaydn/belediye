<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role(['admin']);

$status = $_GET['status'] ?? '';
$department = (int)($_GET['department'] ?? 0);
$q = trim($_GET['q'] ?? '');

$sql = 'SELECT c.id, c.tracking_code, c.title, c.type, c.status, c.priority, c.created_at, c.due_date, d.name AS department_name, u.full_name AS assigned_name
        FROM complaints c
        LEFT JOIN departments d ON d.id = c.department_id
        LEFT JOIN users u ON u.id = c.assigned_user_id
        WHERE 1=1';
$params = [];
if ($status !== '') { $sql .= ' AND c.status = ?'; $params[] = $status; }
if ($department > 0) { $sql .= ' AND c.department_id = ?'; $params[] = $department; }
if ($q !== '') { $sql .= ' AND (c.tracking_code LIKE ? OR c.title LIKE ? OR c.citizen_name LIKE ?)'; $params = array_merge($params, ["%$q%","%$q%","%$q%"]); }
$sql .= ' ORDER BY CASE c.priority WHEN "Acil" THEN 1 WHEN "Yüksek" THEN 2 WHEN "Normal" THEN 3 ELSE 4 END, c.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();

$pageTitle = 'Tüm Başvurular';
require '../includes/panel_header.php';
?>
<div class="panel-card">
    <form method="get" class="filter-bar row g-3">
        <div class="col-lg-4"><input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Takip no, konu veya vatandaş ara"></div>
        <div class="col-lg-3">
            <select name="status" class="form-select">
                <option value="">Tüm Durumlar</option>
                <?php foreach (['Yeni','İnceleniyor','Yönlendirildi','İşlemde','Çözüldü','Reddedildi'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-3">
            <select name="department" class="form-select">
                <option value="0">Tüm Müdürlükler</option>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $department === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 d-grid"><button class="btn btn-primary">Filtrele</button></div>
    </form>

    <div class="table-responsive mt-3">
        <table class="table align-middle">
            <thead><tr><th>Başvuru</th><th>Tür</th><th>Müdürlük / Personel</th><th>Öncelik</th><th>Durum</th><th>Hedef Tarih</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($complaints as $item): ?>
                <tr>
                    <td><strong><?= e($item['tracking_code']) ?></strong><span class="table-title"><?= e($item['title']) ?></span><small><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></small></td>
                    <td><?= e($item['type']) ?></td>
                    <td><span><?= e($item['department_name'] ?: 'Atanmadı') ?></span><small><?= e($item['assigned_name'] ?: 'Personel atanmadı') ?></small></td>
                    <td><span class="badge text-bg-<?= priority_badge($item['priority']) ?>"><?= e($item['priority']) ?></span></td>
                    <td><span class="badge text-bg-<?= status_badge($item['status']) ?>"><?= e($item['status']) ?></span></td>
                    <td><?= $item['due_date'] ? date('d.m.Y', strtotime($item['due_date'])) : '-' ?></td>
                    <td><a href="basvuru_detay.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$complaints): ?><tr><td colspan="7" class="text-center text-muted py-5">Filtreye uygun kayıt bulunamadı.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require '../includes/panel_footer.php'; ?>
