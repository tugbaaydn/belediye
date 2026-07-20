<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_login();

$user = current_user();

$pageTitle = $pageTitle ?? 'Yönetim Paneli';
$basePath = '../';

require __DIR__ . '/header.php';

/*
|--------------------------------------------------------------------------
| Bildirimleri hazırla
|--------------------------------------------------------------------------
*/

$notifications = [];
$notificationCount = 0;

if ($user['role'] === 'admin') {
    /*
    |--------------------------------------------------------------------------
    | Yönetici bildirimleri
    |--------------------------------------------------------------------------
    */

    $newComplaintStatement = $pdo->query("
        SELECT
            id,
            tracking_code,
            title,
            created_at
        FROM complaints
        WHERE status = 'Yeni'
        ORDER BY created_at DESC
        LIMIT 5
    ");

    $newComplaints = $newComplaintStatement->fetchAll();

    foreach ($newComplaints as $newComplaint) {
        $notifications[] = [
            'icon' => 'fa-file-circle-plus',
            'color' => 'primary',
            'title' => 'Yeni başvuru oluşturuldu',
            'message' => $newComplaint['title'],
            'date' => $newComplaint['created_at'],
            'url' => '../admin/basvuru_detay.php?id=' .
                $newComplaint['id']
        ];
    }

    $overdueStatement = $pdo->query("
        SELECT
            id,
            tracking_code,
            title,
            due_date
        FROM complaints
        WHERE due_date < CURDATE()
          AND status NOT IN ('Çözüldü', 'Reddedildi')
        ORDER BY due_date ASC
        LIMIT 5
    ");

    $overdueComplaints = $overdueStatement->fetchAll();

    foreach ($overdueComplaints as $overdueComplaint) {
        $notifications[] = [
            'icon' => 'fa-triangle-exclamation',
            'color' => 'danger',
            'title' => 'Hedef tarihi geçti',
            'message' => $overdueComplaint['title'],
            'date' => $overdueComplaint['due_date'],
            'url' => '../admin/basvuru_detay.php?id=' .
                $overdueComplaint['id']
        ];
    }
} else {
    /*
    |--------------------------------------------------------------------------
    | Personel bildirimleri
    |--------------------------------------------------------------------------
    */

    $assignedStatement = $pdo->prepare("
        SELECT
            id,
            tracking_code,
            title,
            status,
            created_at
        FROM complaints
        WHERE assigned_user_id = ?
          AND status IN ('Yönlendirildi', 'İşlemde')
        ORDER BY updated_at DESC
        LIMIT 8
    ");

    $assignedStatement->execute([
        (int) $user['id']
    ]);

    $assignedComplaints = $assignedStatement->fetchAll();

    foreach ($assignedComplaints as $assignedComplaint) {
        $notificationTitle =
            $assignedComplaint['status'] === 'Yönlendirildi'
            ? 'Yeni başvuru atandı'
            : 'İşlem devam ediyor';

        $notificationIcon =
            $assignedComplaint['status'] === 'Yönlendirildi'
            ? 'fa-user-check'
            : 'fa-spinner';

        $notificationColor =
            $assignedComplaint['status'] === 'Yönlendirildi'
            ? 'primary'
            : 'warning';

        $notifications[] = [
            'icon' => $notificationIcon,
            'color' => $notificationColor,
            'title' => $notificationTitle,
            'message' => $assignedComplaint['title'],
            'date' => $assignedComplaint['created_at'],
            'url' => '../personel/basvuru_detay.php?id=' .
                $assignedComplaint['id']
        ];
    }

    $overdueStatement = $pdo->prepare("
        SELECT
            id,
            tracking_code,
            title,
            due_date
        FROM complaints
        WHERE assigned_user_id = ?
          AND due_date < CURDATE()
          AND status NOT IN ('Çözüldü', 'Reddedildi')
        ORDER BY due_date ASC
        LIMIT 5
    ");

    $overdueStatement->execute([
        (int) $user['id']
    ]);

    $overdueComplaints = $overdueStatement->fetchAll();

    foreach ($overdueComplaints as $overdueComplaint) {
        $notifications[] = [
            'icon' => 'fa-clock',
            'color' => 'danger',
            'title' => 'Başvurunun süresi geçti',
            'message' => $overdueComplaint['title'],
            'date' => $overdueComplaint['due_date'],
            'url' => '../personel/basvuru_detay.php?id=' .
                $overdueComplaint['id']
        ];
    }
}

$notificationCount = count($notifications);

/*
|--------------------------------------------------------------------------
| Bildirimleri tarihe göre sırala
|--------------------------------------------------------------------------
*/

usort(
    $notifications,
    static function (array $first, array $second): int {
        return strtotime($second['date'])
            <=> strtotime($first['date']);
    }
);

$notifications = array_slice($notifications, 0, 8);

?>

<div class="panel-layout">

    <!-- Sol menü -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-brand">
            <i class="fa-solid fa-building-columns"></i>

            <span>
                Akıllı Belediye
            </span>
        </div>

        <div class="sidebar-user">
            <div class="avatar">
                <?= e(mb_substr($user['full_name'], 0, 1)) ?>
            </div>

            <div>
                <strong>
                    <?= e($user['full_name']) ?>
                </strong>

                <small>
                    <?php if ($user['role'] === 'admin'): ?>
                        Sistem Yöneticisi
                    <?php else: ?>
                        Belediye Personeli
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <nav class="sidebar-nav">

            <?php if ($user['role'] === 'admin'): ?>

                <a href="../admin/dashboard.php">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>

                <a href="../admin/basvurular.php">
                    <i class="fa-solid fa-inbox"></i>
                    <span>Tüm Başvurular</span>
                </a>

                <a href="../admin/atamalar.php">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Personel Atama</span>
                </a>
                
                <a href="../admin/departmanlar.php">
                    <i class="fa-solid fa-sitemap"></i>
                    <span>Müdürlükler</span>
                </a>

                <a href="../admin/kullanicilar.php">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Kullanıcılar</span>
                </a>

            <?php else: ?>

                <a href="../personel/dashboard.php">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>

                <a href="../personel/basvurularim.php">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Başvurularım</span>
                </a>

            <?php endif; ?>

            <a href="../index.php" target="_blank">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Vatandaş Sitesi</span>
            </a>

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Çıkış</span>
            </a>

        </nav>
    </aside>

    <!-- Sayfa içeriği -->
    <main class="panel-main">

        <header class="panel-topbar">

            <div class="topbar-left">

                <button type="button" class="sidebar-toggle" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="topbar-title">
                    <h1><?= e($pageTitle) ?></h1>

                    <p>
                        <?= date('d.m.Y') ?>
                        · Belediye hizmet yönetimi
                    </p>
                </div>

            </div>

            <div class="topbar-right">

                <!-- Bildirim alanı -->
                <div class="dropdown notification-dropdown">

                    <button type="button" class="notification-button" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" aria-expanded="false" title="Bildirimler">
                        <i class="fa-regular fa-bell"></i>

                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge">
                                <?= $notificationCount > 9
                                    ? '9+'
                                    : $notificationCount
                                    ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end notification-menu shadow">
                        <div class="notification-header">
                            <div>
                                <strong>Bildirimler</strong>

                                <small>
                                    Güncel başvuru hareketleri
                                </small>
                            </div>

                            <?php if ($notificationCount > 0): ?>
                                <span class="badge text-bg-danger">
                                    <?= $notificationCount ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="notification-list">

                            <?php foreach (
                                $notifications as $notification
                            ): ?>

                                <a href="<?= e($notification['url']) ?>" class="notification-item">
                                    <div class="notification-icon
                                bg-<?= e($notification['color']) ?>-subtle
                                text-<?= e($notification['color']) ?>">
                                        <i class="fa-solid
                                    <?= e($notification['icon']) ?>"></i>
                                    </div>

                                    <div class="notification-content">
                                        <strong>
                                            <?= e($notification['title']) ?>
                                        </strong>

                                        <p>
                                            <?= e($notification['message']) ?>
                                        </p>

                                        <small>
                                            <i class="fa-regular fa-clock me-1"></i>

                                            <?=
                                                date(
                                                    'd.m.Y H:i',
                                                    strtotime(
                                                        $notification['date']
                                                    )
                                                )
                                                ?>
                                        </small>
                                    </div>
                                </a>

                            <?php endforeach; ?>

                            <?php if (!$notifications): ?>
                                <div class="notification-empty">
                                    <i class="fa-regular fa-bell-slash"></i>

                                    <strong>
                                        Yeni bildirim bulunmuyor
                                    </strong>

                                    <span>
                                        Güncel başvuru hareketleri
                                        burada gösterilecektir.
                                    </span>
                                </div>
                            <?php endif; ?>

                        </div>

                        <div class="notification-footer">
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="../admin/basvurular.php">
                                    Tüm başvuruları görüntüle
                                </a>
                            <?php else: ?>
                                <a href="../personel/basvurularim.php">
                                    Başvurularımı görüntüle
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Kullanıcı bilgisi -->
                <div class="topbar-user">

                    <div>
                        <strong>
                            <?= e($user['full_name']) ?>
                        </strong>

                        <small>
                            <?= $user['role'] === 'admin'
                                ? 'Yönetici'
                                : 'Personel'
                                ?>
                        </small>
                    </div>

                    <div class="topbar-avatar">
                        <?= e(mb_substr($user['full_name'], 0, 1)) ?>
                    </div>

                </div>

            </div>

        </header>

        <div class="panel-content">

            <?php if ($flash): ?>
                <div class="alert alert-<?=
                    e($flash['type'])
                    ?> alert-dismissible fade show">
                    <?= e($flash['message']) ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>