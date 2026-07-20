<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_session();

function require_login(): void {
    if (empty($_SESSION['user'])) {
        flash('warning', 'Bu sayfayı görüntülemek için giriş yapmalısınız.');
        redirect('../login.php');
    }
}

function require_role(array $roles): void {
    require_login();
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        exit('Bu işlem için yetkiniz bulunmuyor.');
    }
}

function current_user(): ?array {
    start_session();
    return $_SESSION['user'] ?? null;
}
