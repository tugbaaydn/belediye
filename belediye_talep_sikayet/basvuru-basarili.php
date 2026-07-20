<?php
require_once 'includes/functions.php';
start_session();
$code = $_SESSION['created_tracking_code'] ?? null;
unset($_SESSION['created_tracking_code']);
if (!$code) redirect('index.php');

$pageTitle = 'Başvuru Alındı';
$basePath = '';
require 'includes/header.php';
require 'includes/public_nav.php';
?>
<section class="success-section">
    <div class="container">
        <div class="success-card">
            <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h1>Başvurunuz başarıyla alındı</h1>
            <p>Takip numaranızı kaydedin. Başvurunuzun durumunu bu numara ve telefon bilginizle takip edebilirsiniz.</p>
            <div class="tracking-code"><?= e($code) ?></div>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="talep-takip.php?code=<?= urlencode($code) ?>" class="btn btn-primary">Başvuruyu Takip Et</a>
                <a href="index.php" class="btn btn-outline-secondary">Ana Sayfaya Dön</a>
            </div>
        </div>
    </div>
</section>
<?php require 'includes/footer.php'; ?>
