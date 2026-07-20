<?php

declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['staff']);

$user = current_user();
$complaintId = (int)($_GET['id'] ?? 0);

$allowedStatuses = [
    'Yönlendirildi',
    'İşlemde',
    'Çözüldü',
    'Reddedildi'
];

/*
|--------------------------------------------------------------------------
| Başvuru ID kontrolü
|--------------------------------------------------------------------------
*/

if ($complaintId <= 0) {
    flash('danger', 'Geçersiz başvuru numarası.');
    redirect('basvurularim.php');
}

/*
|--------------------------------------------------------------------------
| Personelin kendisine atanmış başvurusunu getir
|--------------------------------------------------------------------------
*/

$complaintStatement = $pdo->prepare("
    SELECT
        c.*,
        d.name AS department_name,
        suggested_department.name AS suggested_department_name,
        assigned_by_user.full_name AS assigned_by_name

    FROM complaints AS c

    LEFT JOIN departments AS d
        ON d.id = c.department_id

    LEFT JOIN departments AS suggested_department
        ON suggested_department.id = c.suggested_department_id

    LEFT JOIN users AS assigned_by_user
        ON assigned_by_user.id = c.assigned_by

    WHERE c.id = ?
      AND c.assigned_user_id = ?

    LIMIT 1
");

$complaintStatement->execute([
    $complaintId,
    (int)$user['id']
]);

$complaint = $complaintStatement->fetch();

if (!$complaint) {
    flash(
        'danger',
        'Başvuru bulunamadı veya bu başvuru size atanmamış.'
    );

    redirect('basvurularim.php');
}

/*
|--------------------------------------------------------------------------
| İşlem kaydetme
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $newStatus = trim($_POST['status'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $isPublic = 0;

    /*
    |--------------------------------------------------------------------------
    | Form kontrolleri
    |--------------------------------------------------------------------------
    */

    if (!in_array($newStatus, $allowedStatuses, true)) {
        flash('danger', 'Geçersiz başvuru durumu seçildi.');

        redirect(
            'basvuru_detay.php?id=' . $complaintId
        );
    }

    if (mb_strlen($note) < 5) {
        flash(
            'danger',
            'İşlem açıklaması en az 5 karakter olmalıdır.'
        );

        redirect(
            'basvuru_detay.php?id=' . $complaintId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | İşlem fotoğrafı yükleme
    |--------------------------------------------------------------------------
    */

    $operationPhoto = null;
    $operationPhotoFullPath = null;

    if (
        isset($_FILES['operation_photo']) &&
        ($_FILES['operation_photo']['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_NO_FILE
    ) {
        if (
            $_FILES['operation_photo']['error']
            !== UPLOAD_ERR_OK
        ) {
            flash(
                'danger',
                'İşlem fotoğrafı yüklenirken bir hata oluştu.'
            );

            redirect(
                'basvuru_detay.php?id=' . $complaintId
            );
        }

        $maximumFileSize = 5 * 1024 * 1024;

        if (
            $_FILES['operation_photo']['size']
            > $maximumFileSize
        ) {
            flash(
                'danger',
                'İşlem fotoğrafı en fazla 5 MB olabilir.'
            );

            redirect(
                'basvuru_detay.php?id=' . $complaintId
            );
        }

        $allowedPhotoTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        $temporaryFile = $_FILES['operation_photo']['tmp_name'];

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryFile);

        if (!isset($allowedPhotoTypes[$mimeType])) {
            flash(
                'danger',
                'Fotoğraf JPG, PNG veya WEBP formatında olmalıdır.'
            );

            redirect(
                'basvuru_detay.php?id=' . $complaintId
            );
        }

        $operationPhoto =
            'operation_' .
            date('YmdHis') .
            '_' .
            bin2hex(random_bytes(10)) .
            '.' .
            $allowedPhotoTypes[$mimeType];

        $operationsDirectory =
            __DIR__ . '/../uploads/operations/';

        if (!is_dir($operationsDirectory)) {
            if (
                !mkdir(
                    $operationsDirectory,
                    0775,
                    true
                ) &&
                !is_dir($operationsDirectory)
            ) {
                flash(
                    'danger',
                    'İşlem fotoğrafı klasörü oluşturulamadı.'
                );

                redirect(
                    'basvuru_detay.php?id=' . $complaintId
                );
            }
        }

        $operationPhotoFullPath =
            $operationsDirectory .
            $operationPhoto;

        if (
            !move_uploaded_file(
                $temporaryFile,
                $operationPhotoFullPath
            )
        ) {
            flash(
                'danger',
                'İşlem fotoğrafı sunucuya kaydedilemedi.'
            );

            redirect(
                'basvuru_detay.php?id=' . $complaintId
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Başvuruyu ve işlem geçmişini güncelle
    |--------------------------------------------------------------------------
    */

    try {
        $pdo->beginTransaction();

        if (
            $newStatus === 'Çözüldü' ||
            $newStatus === 'Reddedildi'
        ) {
            $updateStatement = $pdo->prepare("
                UPDATE complaints

                SET
                    status = ?,
                    resolution_note = ?,
                    resolved_at = NOW()

                WHERE id = ?
                  AND assigned_user_id = ?
            ");

            $updateStatement->execute([
                $newStatus,
                $note,
                $complaintId,
                (int)$user['id']
            ]);
        } else {
            $updateStatement = $pdo->prepare("
                UPDATE complaints

                SET
                    status = ?,
                    resolved_at = NULL

                WHERE id = ?
                  AND assigned_user_id = ?
            ");

            $updateStatement->execute([
                $newStatus,
                $complaintId,
                (int)$user['id']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | İşlem geçmişine açıklama ve fotoğraf kaydet
        |--------------------------------------------------------------------------
        */

        $historyStatement = $pdo->prepare("
            INSERT INTO complaint_history (
                complaint_id,
                user_id,
                status,
                note,
                attachment,
                is_public
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $historyStatement->execute([
            $complaintId,
            (int)$user['id'],
            $newStatus,
            $note,
            $operationPhoto,
            $isPublic
        ]);

        $pdo->commit();

        if ($operationPhoto) {
            flash(
                'success',
                'İşlem açıklaması ve fotoğraf başarıyla kaydedildi.'
            );
        } else {
            flash(
                'success',
                'İşlem açıklaması başarıyla kaydedildi.'
            );
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $operationPhotoFullPath &&
            file_exists($operationPhotoFullPath)
        ) {
            unlink($operationPhotoFullPath);
        }

        flash(
            'danger',
            'İşlem kaydedilirken bir hata oluştu.'
        );
    }

    redirect(
        'basvuru_detay.php?id=' . $complaintId
    );
}

/*
|--------------------------------------------------------------------------
| İşlem geçmişini getir
|--------------------------------------------------------------------------
*/

$historyStatement = $pdo->prepare("
    SELECT
        history.*,
        history_user.full_name AS user_full_name

    FROM complaint_history AS history

    LEFT JOIN users AS history_user
        ON history_user.id = history.user_id

    WHERE history.complaint_id = ?

    ORDER BY
        history.created_at DESC,
        history.id DESC
");

$historyStatement->execute([$complaintId]);

$history = $historyStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Google Maps bağlantısı
|--------------------------------------------------------------------------
*/

$mapUrl = null;

if (
    !empty($complaint['latitude']) &&
    !empty($complaint['longitude'])
) {
    $coordinates =
        $complaint['latitude'] .
        ',' .
        $complaint['longitude'];

    $mapUrl =
        'https://www.google.com/maps?q=' .
        urlencode($coordinates);
}

/*
|--------------------------------------------------------------------------
| Hedef tarih kontrolü
|--------------------------------------------------------------------------
*/

$isOverdue = false;

if (
    !empty($complaint['due_date']) &&
    !in_array(
        $complaint['status'],
        ['Çözüldü', 'Reddedildi'],
        true
    )
) {
    $isOverdue =
        strtotime($complaint['due_date']) <
        strtotime(date('Y-m-d'));
}

$pageTitle = 'Başvuru İşlemi';

require '../includes/panel_header.php';
?>

<div class="detail-top">

    <div>
        <span class="text-muted">
            <?= e($complaint['tracking_code']) ?>
        </span>

        <h2>
            <?= e($complaint['title']) ?>
        </h2>

        <div class="d-flex flex-wrap gap-2">

            <span
                class="badge text-bg-<?=
                    status_badge($complaint['status'])
                ?>"
            >
                <?= e($complaint['status']) ?>
            </span>

            <span
                class="badge text-bg-<?=
                    priority_badge($complaint['priority'])
                ?>"
            >
                <?= e($complaint['priority']) ?>
            </span>

            <span class="badge text-bg-light">
                <?= e($complaint['type']) ?>
            </span>

        </div>
    </div>

    <a
        href="basvurularim.php"
        class="btn btn-outline-secondary"
    >
        <i class="fa-solid fa-arrow-left me-1"></i>
        Listeye Dön
    </a>

</div>

<?php if ($isOverdue): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>

        Bu başvurunun son işlem tarihi geçmiştir.
    </div>
<?php endif; ?>

<div class="row g-4">

    <!-- Sol alan -->
    <div class="col-xl-8">

        <!-- Görev bilgileri -->
        <div class="panel-card mb-4">

            <div class="panel-card-header">
                <div>
                    <h3>Görev Bilgileri</h3>

                    <p>
                        Yönetici tarafından size atanan görev
                    </p>
                </div>
            </div>

            <div class="detail-grid">

                <div>
                    <small>İlgili Müdürlük</small>

                    <strong>
                        <?= e(
                            $complaint['department_name']
                            ?: 'Müdürlük belirlenmedi'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Öncelik Seviyesi</small>

                    <strong>
                        <?= e($complaint['priority']) ?>
                    </strong>
                </div>

                <div>
                    <small>Görevi Atayan</small>

                    <strong>
                        <?= e(
                            $complaint['assigned_by_name']
                            ?: 'Sistem yöneticisi'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Görevlendirme Tarihi</small>

                    <strong>
                        <?php if (
                            !empty($complaint['assigned_at'])
                        ): ?>
                            <?= date(
                                'd.m.Y H:i',
                                strtotime(
                                    $complaint['assigned_at']
                                )
                            ) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </strong>
                </div>

                <div>
                    <small>Son İşlem Tarihi</small>

                    <strong
                        class="<?= $isOverdue
                            ? 'text-danger'
                            : ''
                        ?>"
                    >
                        <?php if (
                            !empty($complaint['due_date'])
                        ): ?>
                            <?= date(
                                'd.m.Y',
                                strtotime(
                                    $complaint['due_date']
                                )
                            ) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </strong>
                </div>

                <div>
                    <small>Mevcut Durum</small>

                    <strong>
                        <?= e($complaint['status']) ?>
                    </strong>
                </div>

            </div>

            <?php if (
                !empty($complaint['assignment_description'])
            ): ?>
                <div class="assignment-description-box mt-4">

                    <div class="assignment-description-icon">
                        <i class="fa-solid fa-list-check"></i>
                    </div>

                    <div>
                        <strong>Görev Açıklaması</strong>

                        <p>
                            <?= nl2br(
                                e(
                                    $complaint[
                                        'assignment_description'
                                    ]
                                )
                            ) ?>
                        </p>
                    </div>

                </div>
            <?php endif; ?>

        </div>

        <!-- Başvuru bilgileri -->
        <div class="panel-card mb-4">

            <div class="panel-card-header">
                <div>
                    <h3>Başvuru Bilgileri</h3>

                    <p>
                        Vatandaş tarafından iletilen başvuru detayları
                    </p>
                </div>
            </div>

            <div class="complaint-content">

                <h4 class="fw-bold mb-3">
                    <?= e($complaint['title']) ?>
                </h4>

                <p>
                    <?= nl2br(e($complaint['description'])) ?>
                </p>

                <div class="detail-grid">

                    <div>
                        <small>Vatandaş</small>

                        <strong>
                            <?= e($complaint['citizen_name']) ?>
                        </strong>
                    </div>

                    <!-- Telefon numarası personele gösterilmez -->

                    <div>
                        <small>E-posta</small>

                        <strong>
                            <?= e(
                                $complaint['email']
                                ?: '-'
                            ) ?>
                        </strong>
                    </div>

                    <div>
                        <small>Başvuru Türü</small>

                        <strong>
                            <?= e($complaint['type']) ?>
                        </strong>
                    </div>

                    <div>
                        <small>Mahalle</small>

                        <strong>
                            <?= e(
                                $complaint['district']
                                ?: '-'
                            ) ?>
                        </strong>
                    </div>

                    <div>
                        <small>Adres</small>

                        <strong>
                            <?= e(
                                $complaint['address']
                                ?: '-'
                            ) ?>
                        </strong>

                        <?php if ($mapUrl): ?>
                            <a
                                href="<?= e($mapUrl) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="map-link"
                            >
                                <i
                                    class="fa-solid
                                    fa-map-location-dot"
                                ></i>

                                Haritada Aç
                            </a>
                        <?php endif; ?>
                    </div>

                    <div>
                        <small>Oluşturulma Tarihi</small>

                        <strong>
                            <?= date(
                                'd.m.Y H:i',
                                strtotime(
                                    $complaint['created_at']
                                )
                            ) ?>
                        </strong>
                    </div>

                </div>

                <!-- Vatandaşın yüklediği fotoğraf -->
                <?php if (
                    !empty($complaint['attachment'])
                ): ?>
                    <div class="complaint-photo mt-4">

                        <h5 class="fw-bold">
                            Vatandaşın Yüklediği Fotoğraf
                        </h5>

                        <img
                            src="../uploads/<?=
                                e($complaint['attachment'])
                            ?>"
                            alt="Başvuru fotoğrafı"
                        >

                        <a
                            href="../uploads/<?=
                                e($complaint['attachment'])
                            ?>"
                            target="_blank"
                            class="btn btn-outline-primary"
                        >
                            <i class="fa-solid fa-image me-1"></i>
                            Fotoğrafı Büyüt
                        </a>

                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-4 mb-0">
                        Başvuru fotoğrafı bulunmuyor.
                    </div>
                <?php endif; ?>

                <?php if (
                    !empty(
                        $complaint[
                            'suggested_department_name'
                        ]
                    )
                ): ?>
                    <div class="smart-suggestion">

                        <i
                            class="fa-solid
                            fa-wand-magic-sparkles"
                        ></i>

                        <div>
                            <strong>Akıllı Sistem Önerisi</strong>

                            <span>
                                <?= e(
                                    $complaint[
                                        'suggested_department_name'
                                    ]
                                ) ?>

                                birimiyle ilgili olabileceği
                                tahmin edildi.
                            </span>
                        </div>

                    </div>
                <?php endif; ?>

                <?php if (
                    !empty($complaint['resolution_note'])
                ): ?>
                    <div class="resolution-box">

                        <strong>Sonuç Açıklaması</strong>

                        <p>
                            <?= nl2br(
                                e(
                                    $complaint[
                                        'resolution_note'
                                    ]
                                )
                            ) ?>
                        </p>

                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- İşlem geçmişi -->
        <div class="panel-card">

            <div class="panel-card-header">
                <div>
                    <h3>İşlem Geçmişi</h3>

                    <p>
                        Başvuru üzerinde yapılan bütün işlemler
                    </p>
                </div>
            </div>

            <div class="admin-timeline">

                <?php foreach (
                    $history as $historyItem
                ): ?>
                    <div class="admin-timeline-item">

                        <div class="timeline-dot"></div>

                        <div>

                            <div
                                class="d-flex
                                justify-content-between
                                align-items-start
                                gap-3"
                            >
                                <strong>
                                    <?= e(
                                        $historyItem['status']
                                    ) ?>
                                </strong>

                                <small>
                                    <?= date(
                                        'd.m.Y H:i',
                                        strtotime(
                                            $historyItem[
                                                'created_at'
                                            ]
                                        )
                                    ) ?>
                                </small>
                            </div>

                            <p>
                                <?= nl2br(
                                    e($historyItem['note'])
                                ) ?>
                            </p>

                            <!-- İşlem fotoğrafı -->
                            <?php if (
                                !empty(
                                    $historyItem['attachment']
                                )
                            ): ?>
                                <div
                                    class="history-operation-photo"
                                >
                                    <img
                                        src="../uploads/operations/<?=
                                            e(
                                                $historyItem[
                                                    'attachment'
                                                ]
                                            )
                                        ?>"
                                        alt="Yapılan işleme ait fotoğraf"
                                    >

                                    <a
                                        href="../uploads/operations/<?=
                                            e(
                                                $historyItem[
                                                    'attachment'
                                                ]
                                            )
                                        ?>"
                                        target="_blank"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        <i
                                            class="fa-solid
                                            fa-image me-1"
                                        ></i>

                                        Fotoğrafı Büyüt
                                    </a>
                                </div>
                            <?php endif; ?>

                            <small>
                                <?= e(
                                    $historyItem[
                                        'user_full_name'
                                    ]
                                    ?: 'Sistem / Vatandaş'
                                ) ?>

                                ·

                                <?php if (
                                    (int)$historyItem['is_public']
                                    === 1
                                ): ?>
                                    Vatandaşa açık
                                <?php else: ?>
                                    Kurum içi not
                                <?php endif; ?>
                            </small>

                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$history): ?>
                    <div
                        class="text-center
                        text-muted
                        py-4"
                    >
                        İşlem geçmişi bulunmuyor.
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- Sağ işlem alanı -->
    <div class="col-xl-4">

        <div class="panel-card">

            <div class="panel-card-header">
                <div>
                    <h3>İşlem Kaydet</h3>

                    <p>
                        Durum, açıklama ve fotoğraf ekleyin
                    </p>
                </div>
            </div>

            <form
                method="post"
                enctype="multipart/form-data"
                class="vstack gap-3"
                id="operationForm"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <div>
                    <label class="form-label">
                        Yeni Durum
                    </label>

                    <select
                        name="status"
                        class="form-select"
                        required
                    >
                        <?php foreach (
                            $allowedStatuses as $status
                        ): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= $complaint['status']
                                    === $status
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">
                        İşlem Açıklaması
                    </label>

                    <textarea
                        name="note"
                        class="form-control"
                        rows="6"
                        placeholder="Yapılan işlemi, saha çalışmasını veya çözüm sonucunu açıklayınız..."
                        required
                    ></textarea>

                    <div class="form-text">
                        Açıklama en az 5 karakter olmalıdır.
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        Yapılan İşleme Ait Fotoğraf
                    </label>

                    <input
                        type="file"
                        name="operation_photo"
                        id="operationPhoto"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                    >

                    <div class="form-text">
                        JPG, PNG veya WEBP formatında en fazla
                        5 MB fotoğraf ekleyebilirsiniz.
                    </div>

                    <div
                        id="operationPhotoPreviewBox"
                        class="photo-preview-wrap d-none"
                    >
                        <img
                            id="operationPhotoPreview"
                            alt="İşlem fotoğrafı önizlemesi"
                        >
                    </div>
                </div>

        

                <button
                    type="submit"
                    class="btn btn-success"
                >
                    <i
                        class="fa-solid
                        fa-floppy-disk me-2"
                    ></i>

                    İşlemi Kaydet
                </button>

            </form>
        </div>

        <div class="panel-card mt-4">

            <div class="panel-card-header">
                <div>
                    <h3>Durum Açıklamaları</h3>
                </div>
            </div>

            <div class="vstack gap-3">

                <div>
                    <span class="badge text-bg-primary">
                        Yönlendirildi
                    </span>

                    <small class="d-block text-muted mt-1">
                        Görev personele atanmıştır.
                    </small>
                </div>

                <div>
                    <span class="badge text-bg-warning">
                        İşlemde
                    </span>

                    <small class="d-block text-muted mt-1">
                        Başvuruyla ilgili çalışmalar devam etmektedir.
                    </small>
                </div>

                <div>
                    <span class="badge text-bg-success">
                        Çözüldü
                    </span>

                    <small class="d-block text-muted mt-1">
                        Başvuru sonuçlandırılmıştır.
                    </small>
                </div>

                <div>
                    <span class="badge text-bg-danger">
                        Reddedildi
                    </span>

                    <small class="d-block text-muted mt-1">
                        İşlemin gerçekleştirilememe nedeni
                        açıklanmalıdır.
                    </small>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const operationPhoto =
        document.getElementById('operationPhoto');

    const previewBox =
        document.getElementById(
            'operationPhotoPreviewBox'
        );

    const previewImage =
        document.getElementById(
            'operationPhotoPreview'
        );

    if (
        !operationPhoto ||
        !previewBox ||
        !previewImage
    ) {
        return;
    }

    operationPhoto.addEventListener(
        'change',
        function () {
            const file = operationPhoto.files[0];

            if (!file) {
                previewBox.classList.add('d-none');
                previewImage.removeAttribute('src');
                return;
            }

            if (!file.type.startsWith('image/')) {
                previewBox.classList.add('d-none');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert(
                    'Seçilen fotoğraf en fazla 5 MB olabilir.'
                );

                operationPhoto.value = '';
                previewBox.classList.add('d-none');
                return;
            }

            previewImage.src =
                URL.createObjectURL(file);

            previewBox.classList.remove('d-none');
        }
    );
});
</script>

<?php require '../includes/panel_footer.php'; ?>