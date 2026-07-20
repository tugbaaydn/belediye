<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role(['admin']);

$stats = [
    'total' => (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn(),
    'new' => (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Yeni'")->fetchColumn(),
    'active' => (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('İnceleniyor','Yönlendirildi','İşlemde')")->fetchColumn(),
    'resolved' => (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Çözüldü'")->fetchColumn(),
];
$overdue = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE due_date < CURDATE() AND status NOT IN ('Çözüldü','Reddedildi')")->fetchColumn();
$recent = $pdo->query('SELECT c.id, c.tracking_code, c.title, c.type, c.status, c.priority, c.created_at, d.name AS department_name FROM complaints c LEFT JOIN departments d ON d.id = c.department_id ORDER BY c.created_at DESC LIMIT 8')->fetchAll();
$departmentStats = $pdo->query("SELECT d.name, COUNT(c.id) total FROM departments d LEFT JOIN complaints c ON c.department_id = d.id GROUP BY d.id ORDER BY total DESC LIMIT 6")->fetchAll();

$pageTitle = 'Yönetim Dashboard';
require '../includes/panel_header.php';
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-inbox"></i></div><div><span>Toplam Başvuru</span><strong><?= $stats['total'] ?></strong></div></div>
    <div class="stat-card"><div class="stat-icon bg-secondary-subtle text-secondary"><i class="fa-solid fa-sparkles"></i></div><div><span>Yeni Başvurular</span><strong><?= $stats['new'] ?></strong></div></div>
    <div class="stat-card"><div class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-spinner"></i></div><div><span>Devam Eden</span><strong><?= $stats['active'] ?></strong></div></div>
    <div class="stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="fa-solid fa-circle-check"></i></div><div><span>Çözülen</span><strong><?= $stats['resolved'] ?></strong></div></div>
</div>

<?php if ($overdue > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2"><i class="fa-solid fa-triangle-exclamation"></i><strong><?= $overdue ?></strong> başvurunun hedef çözüm tarihi geçti.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel-card">
            <div class="panel-card-header"><div><h3>Son Başvurular</h3><p>En son oluşturulan vatandaş kayıtları</p></div><a href="basvurular.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Takip No</th><th>Konu</th><th>Müdürlük</th><th>Durum</th><th>Tarih</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $item): ?>
                        <tr>
                            <td><a href="basvuru_detay.php?id=<?= $item['id'] ?>" class="fw-bold"><?= e($item['tracking_code']) ?></a></td>
                            <td><span class="table-title"><?= e($item['title']) ?></span><small><?= e($item['type']) ?> · <?= e($item['priority']) ?></small></td>
                            <td><?= e($item['department_name'] ?: 'Atanmadı') ?></td>
                            <td><span class="badge text-bg-<?= status_badge($item['status']) ?>"><?= e($item['status']) ?></span></td>
                            <td><?= date('d.m.Y', strtotime($item['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-5">Henüz başvuru bulunmuyor.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel-card">
            <div class="panel-card-header"><div><h3>Müdürlük Dağılımı</h3><p>Başvuru yoğunluğu</p></div></div>
            <div class="department-list">
                <?php $max = max(array_column($departmentStats ?: [['total'=>1]], 'total')); ?>
                <?php foreach ($departmentStats as $row): ?>
                    <div>
                        <div class="d-flex justify-content-between"><span><?= e($row['name']) ?></span><strong><?= $row['total'] ?></strong></div>
                        <div class="progress"><div class="progress-bar" style="width: <?= $max ? round(($row['total']/$max)*100) : 0 ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require '../includes/panel_footer.php'; ?>
