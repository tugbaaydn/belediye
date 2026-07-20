<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $role = $_POST['role'] ?? 'staff';

    if ($fullName && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 6) {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role, department_id) VALUES (?, ?, ?, ?, NULLIF(?,0))');
            $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $departmentId]);
            flash('success', 'Kullanıcı oluşturuldu.');
        } catch (PDOException $e) {
            flash('danger', 'Bu e-posta adresi daha önce kullanılmış olabilir.');
        }
    } else {
        flash('danger', 'Bilgileri kontrol edin. Şifre en az 6 karakter olmalıdır.');
    }
    redirect('kullanicilar.php');
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id !== (int)current_user()['id']) {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Kullanıcı durumu güncellendi.');
    }
    redirect('kullanicilar.php');
}

$users = $pdo->query('SELECT u.*, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.created_at DESC')->fetchAll();
$departments = $pdo->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();

$pageTitle = 'Kullanıcı Yönetimi';
require '../includes/panel_header.php';
?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel-card">
            <div class="panel-card-header"><div><h3>Personel ve Yöneticiler</h3></div></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Kullanıcı</th><th>Rol</th><th>Müdürlük</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><strong><?= e($row['full_name']) ?></strong><small><?= e($row['email']) ?></small></td>
                            <td><?= $row['role'] === 'admin' ? 'Yönetici' : 'Personel' ?></td>
                            <td><?= e($row['department_name'] ?: '-') ?></td>
                            <td><span class="badge text-bg-<?= $row['is_active'] ? 'success' : 'secondary' ?>"><?= $row['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
                            <td><?php if ((int)$row['id'] !== (int)current_user()['id']): ?><a href="?toggle=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary">Durumu Değiştir</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel-card">
            <div class="panel-card-header"><div><h3>Yeni Kullanıcı</h3></div></div>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div><label class="form-label">Ad Soyad</label><input type="text" name="full_name" class="form-control" required></div>
                <div><label class="form-label">E-posta</label><input type="email" name="email" class="form-control" required></div>
                <div><label class="form-label">Geçici Şifre</label><input type="password" name="password" class="form-control" minlength="6" required></div>
                <div><label class="form-label">Rol</label><select name="role" class="form-select"><option value="staff">Personel</option><option value="admin">Yönetici</option></select></div>
                <div><label class="form-label">Müdürlük</label><select name="department_id" class="form-select"><option value="0">Müdürlük seçilmedi</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-primary">Kullanıcı Ekle</button>
            </form>
        </div>
    </div>
</div>
<?php require '../includes/panel_footer.php'; ?>
