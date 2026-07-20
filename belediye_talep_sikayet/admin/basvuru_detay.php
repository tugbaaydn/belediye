<?php

declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['admin']);

$user = current_user();
$complaintId = (int) ($_GET['id'] ?? 0);

if ($complaintId <= 0) {
    flash('danger', 'Geçersiz başvuru numarası.');
    redirect('basvurular.php');
}

/*
|--------------------------------------------------------------------------
| Kullanılabilecek değerler
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'Yeni',
    'İnceleniyor',
    'Yönlendirildi',
    'İşlemde',
    'Çözüldü',
    'Reddedildi'
];

$allowedPriorities = [
    'Düşük',
    'Normal',
    'Yüksek',
    'Acil'
];

/*
|--------------------------------------------------------------------------
| Başvurunun varlığını kontrol et
|--------------------------------------------------------------------------
*/

$checkStatement = $pdo->prepare("
    SELECT id
    FROM complaints
    WHERE id = ?
    LIMIT 1
");

$checkStatement->execute([$complaintId]);

if (!$checkStatement->fetch()) {
    flash('danger', 'Başvuru bulunamadı.');
    redirect('basvurular.php');
}

/*
|--------------------------------------------------------------------------
| Form işlemleri
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Müdürlüğe yönlendirme ve personel atama
    |--------------------------------------------------------------------------
    */

    if ($action === 'assign') {
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $assignedUserId = (int) ($_POST['assigned_user_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'Normal');

        if (!in_array($priority, $allowedPriorities, true)) {
            flash('danger', 'Geçersiz öncelik seviyesi seçildi.');
            redirect('basvuru_detay.php?id=' . $complaintId);
        }

        $departmentStatement = $pdo->prepare("
            SELECT id, name
            FROM departments
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");

        $departmentStatement->execute([$departmentId]);
        $department = $departmentStatement->fetch();

        if (!$department) {
            flash('danger', 'Geçerli bir müdürlük seçmelisiniz.');
            redirect('basvuru_detay.php?id=' . $complaintId);
        }

        $assignedUser = null;

        if ($assignedUserId > 0) {
            $userStatement = $pdo->prepare("
                SELECT id, full_name, department_id
                FROM users
                WHERE id = ?
                  AND role = 'staff'
                  AND is_active = 1
                LIMIT 1
            ");

            $userStatement->execute([$assignedUserId]);
            $assignedUser = $userStatement->fetch();

            if (!$assignedUser) {
                flash('danger', 'Seçilen personel bulunamadı.');
                redirect('basvuru_detay.php?id=' . $complaintId);
            }

            if ((int) $assignedUser['department_id'] !== $departmentId) {
                flash(
                    'danger',
                    'Seçilen personel bu müdürlüğe bağlı değildir.'
                );

                redirect('basvuru_detay.php?id=' . $complaintId);
            }
        }

        $newStatus = $assignedUserId > 0
            ? 'Yönlendirildi'
            : 'İnceleniyor';

        $dueDate = due_date_for_priority($priority);

        try {
            $pdo->beginTransaction();

            $updateStatement = $pdo->prepare("
                UPDATE complaints
                SET
                    department_id = ?,
                    assigned_user_id = ?,
                    priority = ?,
                    status = ?,
                    due_date = ?
                WHERE id = ?
            ");

            $updateStatement->execute([
                $departmentId,
                $assignedUserId > 0 ? $assignedUserId : null,
                $priority,
                $newStatus,
                $dueDate,
                $complaintId
            ]);

            if ($assignedUser) {
                $historyNote =
                    'Başvuru ' .
                    $department['name'] .
                    ' birimine yönlendirildi ve ' .
                    $assignedUser['full_name'] .
                    ' isimli personele atandı.';
            } else {
                $historyNote =
                    'Başvuru ' .
                    $department['name'] .
                    ' birimine yönlendirildi. Henüz personel atanmadı.';
            }

            $historyStatement = $pdo->prepare("
                INSERT INTO complaint_history (
                    complaint_id,
                    user_id,
                    status,
                    note,
                    is_public
                )
                VALUES (?, ?, ?, ?, 1)
            ");

            $historyStatement->execute([
                $complaintId,
                (int) $user['id'],
                $newStatus,
                $historyNote
            ]);

            $pdo->commit();

            flash(
                'success',
                'Başvuru yönlendirme ve personel bilgileri güncellendi.'
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash(
                'danger',
                'Yönlendirme işlemi sırasında bir hata oluştu.'
            );
        }

        redirect('basvuru_detay.php?id=' . $complaintId);
    }

    /*
    |--------------------------------------------------------------------------
    | Başvuru durumunu güncelleme
    |--------------------------------------------------------------------------
    */

    if ($action === 'status') {
        $newStatus = trim($_POST['status'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if (!in_array($newStatus, $allowedStatuses, true)) {
            flash('danger', 'Geçersiz başvuru durumu seçildi.');
            redirect('basvuru_detay.php?id=' . $complaintId);
        }

        if ($note === '') {
            flash(
                'danger',
                'Yapılan işlemle ilgili bir açıklama yazmalısınız.'
            );

            redirect('basvuru_detay.php?id=' . $complaintId);
        }

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
                ");

                $updateStatement->execute([
                    $newStatus,
                    $note,
                    $complaintId
                ]);
            } else {
                $updateStatement = $pdo->prepare("
                    UPDATE complaints
                    SET
                        status = ?,
                        resolved_at = NULL
                    WHERE id = ?
                ");

                $updateStatement->execute([
                    $newStatus,
                    $complaintId
                ]);
            }

            $historyStatement = $pdo->prepare("
                INSERT INTO complaint_history (
                    complaint_id,
                    user_id,
                    status,
                    note,
                    is_public
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $historyStatement->execute([
                $complaintId,
                (int) $user['id'],
                $newStatus,
                $note,
                $isPublic
            ]);

            $pdo->commit();

            flash(
                'success',
                'Başvurunun durumu ve işlem açıklaması güncellendi.'
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash(
                'danger',
                'Durum güncellenirken bir hata oluştu.'
            );
        }

        redirect('basvuru_detay.php?id=' . $complaintId);
    }

    flash('danger', 'Geçersiz işlem.');
    redirect('basvuru_detay.php?id=' . $complaintId);
}

/*
|--------------------------------------------------------------------------
| Başvuru detayını getir
|--------------------------------------------------------------------------
*/

$complaintStatement = $pdo->prepare("
    SELECT
        c.*,
        d.name AS department_name,
        suggested_department.name AS suggested_department_name,
        assigned_user.full_name AS assigned_name,
        assigned_user.email AS assigned_email
    FROM complaints AS c

    LEFT JOIN departments AS d
        ON d.id = c.department_id

    LEFT JOIN departments AS suggested_department
        ON suggested_department.id = c.suggested_department_id

    LEFT JOIN users AS assigned_user
        ON assigned_user.id = c.assigned_user_id

    WHERE c.id = ?
    LIMIT 1
");

$complaintStatement->execute([$complaintId]);
$complaint = $complaintStatement->fetch();

if (!$complaint) {
    flash('danger', 'Başvuru bulunamadı.');
    redirect('basvurular.php');
}

/*
|--------------------------------------------------------------------------
| Müdürlükleri getir
|--------------------------------------------------------------------------
*/

$departments = $pdo->query("
    SELECT id, name
    FROM departments
    WHERE is_active = 1
    ORDER BY name ASC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Personelleri getir
|--------------------------------------------------------------------------
*/

$staff = $pdo->query("
    SELECT
        id,
        full_name,
        email,
        department_id
    FROM users
    WHERE role = 'staff'
      AND is_active = 1
    ORDER BY full_name ASC
")->fetchAll();

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

    ORDER BY history.created_at DESC, history.id DESC
");

$historyStatement->execute([$complaintId]);
$history = $historyStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Harita bağlantısı
|--------------------------------------------------------------------------
*/

$mapUrl = null;

if (
    $complaint['latitude'] !== null &&
    $complaint['longitude'] !== null
) {
    $coordinates =
        $complaint['latitude'] .
        ',' .
        $complaint['longitude'];

    $mapUrl =
        'https://www.google.com/maps?q=' .
        urlencode($coordinates);
}

$pageTitle = 'Başvuru Detayı';

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

            <span class="badge text-bg-<?=
                status_badge($complaint['status'])
                ?>">
                <?= e($complaint['status']) ?>
            </span>

            <span class="badge text-bg-<?=
                priority_badge($complaint['priority'])
                ?>">
                <?= e($complaint['priority']) ?>
            </span>

            <span class="badge text-bg-light">
                <?= e($complaint['type']) ?>
            </span>

        </div>
    </div>

    <!-- Sağ üst butonlar -->
    <div class="d-flex flex-wrap gap-2">

        <a href="personel_atama.php?id=<?=
            (int) $complaint['id']
            ?>" class="btn btn-primary">
            <i class="fa-solid fa-user-check me-1"></i>
            Personel Ata
        </a>

        <a href="basvurular.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Listeye Dön
        </a>

    </div>

</div>

<div class="row g-4">

    <!-- Başvuru bilgileri -->
    <div class="col-xl-8">

        <div class="panel-card mb-4">
            <div class="panel-card-header">
                <div>
                    <h3>Başvuru Bilgileri</h3>

                    <p>
                        Vatandaş tarafından gönderilen başvuru içeriği
                    </p>
                </div>
            </div>

            <div class="complaint-content">
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

                    <div>
                        <small>Telefon</small>

                        <strong>
                            <?= e($complaint['phone']) ?>
                        </strong>
                    </div>

                    <div>
                        <small>E-posta</small>

                        <strong>
                            <?= e($complaint['email'] ?: '-') ?>
                        </strong>
                    </div>

                    <div>
                        <small>Başvuru Türü</small>

                        <strong>
                            <?= e($complaint['type']) ?>
                        </strong>
                    </div>

                    <div>
                        <small>İlgili Müdürlük</small>

                        <strong>
                            <?=
                                e(
                                    $complaint['department_name']
                                    ?: 'Müdürlük seçilmedi'
                                )
                                ?>
                        </strong>
                    </div>

                    <div>
                        <small>Atanan Personel</small>

                        <strong>
                            <?=
                                e(
                                    $complaint['assigned_name']
                                    ?: 'Personel atanmadı'
                                )
                                ?>
                        </strong>
                    </div>

                    <div>
                        <small>Mahalle / İlçe</small>

                        <strong>
                            <?= e($complaint['district'] ?: '-') ?>
                        </strong>
                    </div>

                    <div>
                        <small>Adres</small>

                        <strong>
                            <?= e($complaint['address'] ?: '-') ?>
                        </strong>

                        <?php if ($mapUrl): ?>
                            <a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener noreferrer" class="map-link">
                                <i class="fa-solid
                                    fa-map-location-dot"></i>

                                Haritada Aç
                            </a>
                        <?php endif; ?>
                    </div>

                    <div>
                        <small>Konum Koordinatları</small>

                        <strong>
                            <?php if ($mapUrl): ?>
                                <?= e($complaint['latitude']) ?>,
                                <?= e($complaint['longitude']) ?>
                            <?php else: ?>
                                Konum bilgisi bulunmuyor
                            <?php endif; ?>
                        </strong>
                    </div>

                    <div>
                        <small>Oluşturulma Tarihi</small>

                        <strong>
                            <?=
                                date(
                                    'd.m.Y H:i',
                                    strtotime($complaint['created_at'])
                                )
                                ?>
                        </strong>
                    </div>

                    <div>
                        <small>Hedef Çözüm Tarihi</small>

                        <strong>
                            <?php if ($complaint['due_date']): ?>
                                <?=
                                    date(
                                        'd.m.Y',
                                        strtotime(
                                            $complaint['due_date']
                                        )
                                    )
                                    ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </strong>
                    </div>

                    <div>
                        <small>Son Güncelleme</small>

                        <strong>
                            <?=
                                date(
                                    'd.m.Y H:i',
                                    strtotime($complaint['updated_at'])
                                )
                                ?>
                        </strong>
                    </div>

                </div>

                <?php if ($complaint['attachment']): ?>
                    <div class="complaint-photo mt-4">
                        <h5 class="fw-bold">
                            Başvuru Fotoğrafı
                        </h5>

                        <img src="../uploads/<?=
                            e($complaint['attachment'])
                            ?>" alt="Vatandaş tarafından yüklenen başvuru fotoğrafı">

                        <a href="../uploads/<?=
                            e($complaint['attachment'])
                            ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="fa-solid fa-image"></i>
                            Fotoğrafı Büyüt
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-4 mb-0">
                        Bu başvuruda fotoğraf bulunmuyor.
                    </div>
                <?php endif; ?>

                <?php if (
                    $complaint['suggested_department_name']
                ): ?>
                    <div class="smart-suggestion">
                        <i class="fa-solid
                            fa-wand-magic-sparkles"></i>

                        <div>
                            <strong>
                                Akıllı Sistem Önerisi
                            </strong>

                            <span>
                                <?=
                                    e(
                                        $complaint[
                                            'suggested_department_name'
                                        ]
                                    )
                                    ?>

                                birimine yönlendirme önerildi.
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($complaint['resolution_note']): ?>
                    <div class="resolution-box">
                        <strong>
                            Sonuç Açıklaması
                        </strong>

                        <p>
                            <?=
                                nl2br(
                                    e(
                                        $complaint[
                                            'resolution_note'
                                        ]
                                    )
                                )
                                ?>
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
                        Başvuru üzerinde gerçekleştirilen bütün işlemler
                    </p>
                </div>
            </div>

            <div class="admin-timeline">

                <?php foreach ($history as $historyItem): ?>
                    <div class="admin-timeline-item">
                        <div class="timeline-dot"></div>

                        <div>
                            <div class="d-flex
                                justify-content-between
                                align-items-start
                                gap-3">
                                <strong>
                                    <?= e($historyItem['status']) ?>
                                </strong>

                                <small>
                                    <?=
                                        date(
                                            'd.m.Y H:i',
                                            strtotime(
                                                $historyItem[
                                                    'created_at'
                                                ]
                                            )
                                        )
                                        ?>
                                </small>
                            </div>

                            <p>
                                <?= nl2br(e($historyItem['note'])) ?>
                            </p>

                            <?php if (!empty($historyItem['attachment'])): ?>
                                <div class="history-operation-photo">
                                    <img src="../uploads/operations/<?=
                                        e($historyItem['attachment'])
                                        ?>" alt="Personelin yaptığı işleme ait fotoğraf">

                                    <a href="../uploads/operations/<?=
                                        e($historyItem['attachment'])
                                        ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-image me-1"></i>
                                        Fotoğrafı Büyüt
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <small>
                                <?=
                                    e(
                                        $historyItem['user_full_name']
                                        ?: 'Sistem / Vatandaş'
                                    )
                                    ?>

                                ·

                                <?php if (
                                    (int) $historyItem['is_public'] === 1
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
                    <div class="text-center text-muted py-4">
                        Bu başvuru için işlem geçmişi bulunmuyor.
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- Sağ işlem alanı -->
    <div class="col-xl-4">

        <!-- Yönlendirme -->
        <div class="panel-card mb-4">
            <div class="panel-card-header">
                <div>
                    <h3>Yönlendirme ve Atama</h3>

                    <p>
                        Müdürlük, personel ve öncelik belirleyin
                    </p>
                </div>
            </div>

            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <input type="hidden" name="action" value="assign">

                <div>
                    <label class="form-label">
                        İlgili Müdürlük
                    </label>

                    <select name="department_id" id="departmentSelect" class="form-select" required>
                        <option value="">
                            Müdürlük seçiniz
                        </option>

                        <?php foreach (
                            $departments as $department
                        ): ?>
                            <option value="<?=
                                (int) $department['id']
                                ?>" <?=
                                (int) $complaint['department_id']
                                === (int) $department['id']
                                ? 'selected'
                                : ''
                                ?>>
                                <?= e($department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">
                        Atanacak Personel
                    </label>

                    <select name="assigned_user_id" id="staffSelect" class="form-select">
                        <option value="0">
                            Personel atanmayacak
                        </option>

                        <?php foreach ($staff as $staffUser): ?>
                            <option value="<?= (int) $staffUser['id'] ?>" data-department="<?=
                                  (int) $staffUser['department_id']
                                  ?>" <?=
                                  (int) $complaint[
                                      'assigned_user_id'
                                  ] === (int) $staffUser['id']
                                  ? 'selected'
                                  : ''
                                  ?>>
                                <?= e($staffUser['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        Müdürlük seçildiğinde yalnızca o birime bağlı
                        personeller gösterilir.
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        Öncelik Seviyesi
                    </label>

                    <select name="priority" class="form-select" required>
                        <?php foreach (
                            $allowedPriorities as $priority
                        ): ?>
                            <option value="<?= e($priority) ?>" <?=
                                  $complaint['priority'] === $priority
                                  ? 'selected'
                                  : ''
                                  ?>>
                                <?= e($priority) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-user-check me-2"></i>
                    Yönlendirmeyi Kaydet
                </button>
            </form>
        </div>

        <!-- Durum güncelleme -->
        <div class="panel-card">
            <div class="panel-card-header">
                <div>
                    <h3>Durum Güncelle</h3>

                    <p>
                        İşlem veya sonuç açıklaması ekleyin
                    </p>
                </div>
            </div>

            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <input type="hidden" name="action" value="status">

                <div>
                    <label class="form-label">
                        Yeni Durum
                    </label>

                    <select name="status" class="form-select" required>
                        <?php foreach (
                            $allowedStatuses as $status
                        ): ?>
                            <option value="<?= e($status) ?>" <?=
                                  $complaint['status'] === $status
                                  ? 'selected'
                                  : ''
                                  ?>>
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">
                        İşlem Açıklaması
                    </label>

                    <textarea name="note" class="form-control" rows="5"
                        placeholder="Yapılan işlemi veya başvurunun sonucunu açıklayın..." required></textarea>
                </div>

                <label class="form-check">
                    <input type="checkbox" name="is_public" class="form-check-input" checked>

                    <span class="form-check-label">
                        Bu açıklama vatandaşa gösterilsin
                    </span>
                </label>

                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-floppy-disk me-2"></i>
                    Durumu Güncelle
                </button>
            </form>
        </div>

    </div>
</div>

<?php require '../includes/panel_footer.php'; ?>