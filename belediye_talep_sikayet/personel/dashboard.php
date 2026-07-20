<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role(['staff']);
$user = current_user();

$stmt = $pdo->prepare("SELECT
    COUNT(*) total,
    SUM(status = 'Yönlendirildi') assigned,
    SUM(status = 'İşlemde') active,
    SUM(status = 'Çözüldü') resolved
    FROM complaints WHERE assigned_user_id = ?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$recentStmt = $pdo->prepare('SELECT id, tracking_code, title, status, priority, due_date, created_at FROM complaints WHERE assigned_user_id = ? ORDER BY created_at DESC LIMIT 8');
$recentStmt->execute([$user['id']]);
$recent = $recentStmt->fetchAll();

$pageTitle = 'Personel Dashboard';
require '../includes/panel_header.php';
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-list-check"></i></div><div><span>Toplam Atanan</span><strong><?= (int)$stats['total'] ?></strong></div></div>
    <div class="stat-card"><div class="stat-icon bg-info-subtle text-info"><i class="fa-solid fa-share"></i></div><div><span>Yeni Atanan</span><strong><?= (int)$stats['assigned'] ?></strong></div></div>
    <div class="stat-card"><div class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-spinner"></i></div><div><span>İşlemde</span><strong><?= (int)$stats['active'] ?></strong></div></div>
    <div class="stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="fa-solid fa-circle-check"></i></div><div><span>Çözülen</span><strong><?= (int)$stats['resolved'] ?></strong></div></div>
</div>

<div class="panel-card">
    <div class="panel-card-header"><div><h3>Son Atanan Başvurular</h3><p>Size yönlendirilen güncel kayıtlar</p></div><a href="basvurularim.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Başvuru</th><th>Öncelik</th><th>Durum</th><th>Hedef Tarih</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $item): ?>
                <tr>
                    <td><strong><?= e($item['tracking_code']) ?></strong><span class="table-title"><?= e($item['title']) ?></span><small><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></small></td>
                    <td><span class="badge text-bg-<?= priority_badge($item['priority']) ?>"><?= e($item['priority']) ?></span></td>
                    <td><span class="badge text-bg-<?= status_badge($item['status']) ?>"><?= e($item['status']) ?></span></td>
                    <td><?= $item['due_date'] ? date('d.m.Y', strtotime($item['due_date'])) : '-' ?></td>
                    <td><a href="basvuru_detay.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">İşlem Yap</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-5">Henüz size atanmış başvuru bulunmuyor.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require '../includes/panel_footer.php'; ?>
