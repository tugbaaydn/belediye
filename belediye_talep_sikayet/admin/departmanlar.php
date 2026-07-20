<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO departments (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description ?: null]);
        flash('success', 'Müdürlük eklendi.');
    }
    redirect('departmanlar.php');
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare('UPDATE departments SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    flash('success', 'Müdürlük durumu güncellendi.');
    redirect('departmanlar.php');
}

$rows = $pdo->query('SELECT d.*, COUNT(DISTINCT u.id) staff_count, COUNT(DISTINCT c.id) complaint_count FROM departments d LEFT JOIN users u ON u.department_id = d.id LEFT JOIN complaints c ON c.department_id = d.id GROUP BY d.id ORDER BY d.name')->fetchAll();

$pageTitle = 'Müdürlük Yönetimi';
require '../includes/panel_header.php';
?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel-card">
            <div class="panel-card-header"><div><h3>Müdürlükler</h3><p>Belediye birimleri ve başvuru dağılımları</p></div></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Müdürlük</th><th>Personel</th><th>Başvuru</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= e($row['name']) ?></strong><small><?= e($row['description'] ?: '-') ?></small></td>
                            <td><?= $row['staff_count'] ?></td>
                            <td><?= $row['complaint_count'] ?></td>
                            <td><span class="badge text-bg-<?= $row['is_active'] ? 'success' : 'secondary' ?>"><?= $row['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
                            <td><a href="?toggle=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">Durumu Değiştir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel-card">
            <div class="panel-card-header"><div><h3>Yeni Müdürlük</h3></div></div>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div><label class="form-label">Müdürlük Adı</label><input type="text" name="name" class="form-control" required></div>
                <div><label class="form-label">Açıklama</label><textarea name="description" class="form-control" rows="4"></textarea></div>
                <button class="btn btn-primary">Müdürlük Ekle</button>
            </form>
        </div>
    </div>
</div>
<?php require '../includes/panel_footer.php'; ?>
