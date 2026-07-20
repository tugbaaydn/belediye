<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$complaint = null;
$history = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = trim($_POST['tracking_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $stmt = $pdo->prepare('
        SELECT c.*, d.name AS department_name, u.full_name AS assigned_name
        FROM complaints c
        LEFT JOIN departments d ON d.id = c.department_id
        LEFT JOIN users u ON u.id = c.assigned_user_id
        WHERE c.tracking_code = ? AND REPLACE(REPLACE(REPLACE(c.phone, " ", ""), "-", ""), "(", "") LIKE ?
    ');
    $normalizedPhone = '%' . str_replace([' ', '-', '(', ')'], '', $phone) . '%';
    $stmt->execute([$code, $normalizedPhone]);
    $complaint = $stmt->fetch();

    if ($complaint) {
        $h = $pdo->prepare('SELECT * FROM complaint_history WHERE complaint_id = ? AND is_public = 1 ORDER BY created_at ASC');
        $h->execute([$complaint['id']]);
        $history = $h->fetchAll();
    } else {
        $error = 'Takip numarası veya telefon bilgisi eşleşmedi.';
    }
}

$pageTitle = 'Başvuru Takip';
$basePath = '';
require 'includes/header.php';
require 'includes/public_nav.php';
?>
<section class="page-hero compact">
    <div class="container">
        <span>Başvuru Takip</span>
        <h1>Başvurunuzun güncel durumunu görüntüleyin</h1>
    </div>
</section>

<section class="form-section">
    <div class="container">
        <div class="tracking-search">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="col-md-5">
                    <label class="form-label">Takip Numarası</label>
                    <input type="text" name="tracking_code" class="form-control form-control-lg"
                        value="<?= e($_GET['code'] ?? $_POST['tracking_code'] ?? '') ?>" placeholder="BLD-2026-XXXXXXXX"
                        required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Telefon Numarası</label>
                    <input type="text" name="phone" class="form-control form-control-lg"
                        placeholder="Başvuruda kullandığınız telefon" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary btn-lg"><i class="fa-solid fa-search"></i> Sorgula</button>
                </div>
            </form>
        </div>

        <?php if ($complaint): ?>
            <div class="tracking-result mt-4">
                <div class="result-header">
                    <div>
                        <span class="small text-uppercase">Takip Numarası</span>
                        <h3><?= e($complaint['tracking_code']) ?></h3>
                    </div>
                    <span
                        class="badge rounded-pill text-bg-<?= status_badge($complaint['status']) ?> fs-6"><?= e($complaint['status']) ?></span>
                </div>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="detail-card">
                            <h4><?= e($complaint['title']) ?></h4>
                            <p><?= nl2br(e($complaint['description'])) ?></p>
                            <div class="detail-grid">
                                <div><small>Tür</small><strong><?= e($complaint['type']) ?></strong></div>
                                <div><small>Öncelik</small><strong><?= e($complaint['priority']) ?></strong></div>
                                <div>
                                    <small>Müdürlük</small><strong><?= e($complaint['department_name'] ?: 'Değerlendiriliyor') ?></strong>
                                </div>
                                <div><small>Sonuçlanma
                                        Hedefi</small><strong><?= $complaint['due_date'] ? date('d.m.Y', strtotime($complaint['due_date'])) : '-' ?></strong>
                                </div>
                                <div>
                                    <small>Oluşturulma</small><strong><?= date('d.m.Y H:i', strtotime($complaint['created_at'])) ?></strong>
                                </div>
                                <div><small>Son
                                        Güncelleme</small><strong><?= date('d.m.Y H:i', strtotime($complaint['updated_at'])) ?></strong>
                                </div>
                            </div>
                            <?php if ($complaint['resolution_note']): ?>
                                <div class="resolution-box"><strong>Sonuç Açıklaması</strong>
                                    <p><?= nl2br(e($complaint['resolution_note'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="timeline-card">
                            <h4>Süreç Geçmişi</h4>
                            <div class="timeline">
                                <?php foreach ($history as $item): ?>
                                    <div class="timeline-item">
                                        <span></span>
                                        <div>
                                            <strong><?= e($item['status']) ?></strong>
                                            <p><?= e($item['note']) ?></p>
                                            <?php if (!empty($item['attachment'])): ?>
                                                <div class="public-operation-photo">
                                                    <img src="uploads/operations/<?=
                                                        e($item['attachment'])
                                                        ?>" alt="Belediye tarafından yapılan işleme ait fotoğraf">

                                                    <a href="uploads/operations/<?=
                                                        e($item['attachment'])
                                                        ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                                        Fotoğrafı Görüntüle
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <small><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require 'includes/footer.php'; ?>