<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
start_session();

if (!empty($_SESSION['user'])) {
    redirect($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'personel/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT u.*, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.email = ? AND u.is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'],
            'department_name' => $user['department_name']
        ];
        redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'personel/dashboard.php');
    }
    $error = 'E-posta veya şifre hatalı.';
}

$pageTitle = 'Personel Girişi';
$basePath = '';
require 'includes/header.php';
?>
<div class="login-page">
    <div class="login-brand-panel">
        <a href="index.php" class="login-logo"><i class="fa-solid fa-building-columns"></i> Akıllı Belediye</a>
        <div>
            <span class="eyebrow">Yönetim Merkezi</span>
            <h1>Başvuruları doğru birime yönlendirin, çözüm sürecini hızlandırın.</h1>
            <p>Belediye personeli ve yöneticileri için güvenli yönetim paneli.</p>
        </div>
    </div>
    <div class="login-form-panel">
        <div class="login-box">
            <h2>Personel Girişi</h2>
            <p>Yetkili hesabınızla sisteme giriş yapın.</p>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="email" class="form-control form-control-lg" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şifre</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" class="form-control form-control-lg" required>
                        <button type="button" data-toggle-password="#password"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg w-100">Giriş Yap</button>
            </form>
            <div class="demo-info">
                <strong>Demo hesapları</strong>
                <span>Admin: admin@belediye.local / Admin123!</span>
                <span>Personel: personel@belediye.local / Personel123!</span>
            </div>
            <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Vatandaş sitesine dön</a>
        </div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
