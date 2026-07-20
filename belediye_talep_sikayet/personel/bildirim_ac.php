<?php

declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['staff']);

$user = current_user();
$notificationId = (int)($_GET['id'] ?? 0);

if ($notificationId <= 0) {
    redirect('dashboard.php');
}

$notificationStatement = $pdo->prepare("
    SELECT
        id,
        complaint_id
    FROM notifications
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");

$notificationStatement->execute([
    $notificationId,
    (int)$user['id']
]);

$notification = $notificationStatement->fetch();

if (!$notification) {
    flash('danger', 'Bildirim bulunamadı.');
    redirect('dashboard.php');
}

$updateStatement = $pdo->prepare("
    UPDATE notifications
    SET
        is_read = 1,
        read_at = NOW()
    WHERE id = ?
      AND user_id = ?
");

$updateStatement->execute([
    $notificationId,
    (int)$user['id']
]);

if (!empty($notification['complaint_id'])) {
    redirect(
        'basvuru_detay.php?id=' .
        (int)$notification['complaint_id']
    );
}

redirect('dashboard.php');