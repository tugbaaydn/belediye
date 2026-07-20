<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role(['staff']);
$user = current_user();
$status = $_GET['status'] ?? '';

$sql = 'SELECT id, tracking_code, title, type, status, priority, due_date, created_at FROM complaints WHERE assigned_user_id = ?';
$params = [$user['id']];
if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
$sql .= ' ORDER BY CASE priority WHEN "Acil" THEN 1 WHEN "Yüksek" THEN 2 ELSE 3 END, created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Başvurularım';
require '../includes/panel_header.php';
?>
<div class="panel-card">
    <form method="get" class="filter-bar row g-3">
        <div class="col-md-9">
            <select name="status" class="form-select">
                <option value="">Tüm Durumlar</option>
                <?php foreach (['Yönlendirildi','İşlemde','Çözüldü','Reddedildi'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-grid"><button class="btn btn-primary">Filtrele</button></div>
    </form>
    <div class="table-responsive mt-3">
        <table class="table align-middle">
            <thead><tr><th>Başvuru</th><th>Tür</th><th>Öncelik</th><th>Durum</th><th>Hedef Tarih</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><strong><?= e($item['tracking_code']) ?></strong><span class="table-title"><?= e($item['title']) ?></span><small><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></small></td>
                    <td><?= e($item['type']) ?></td>
                    <td><span class="badge text-bg-<?= priority_badge($item['priority']) ?>"><?= e($item['priority']) ?></span></td>
                    <td><span class="badge text-bg-<?= status_badge($item['status']) ?>"><?= e($item['status']) ?></span></td>
                    <td><?= $item['due_date'] ? date('d.m.Y', strtotime($item['due_date'])) : '-' ?></td>
                    <td><a href="basvuru_detay.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">Detay</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">Kayıt bulunamadı.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require '../includes/panel_footer.php'; ?>
