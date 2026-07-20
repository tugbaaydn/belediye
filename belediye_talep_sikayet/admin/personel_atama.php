<?php

declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['admin']);

$currentUser = current_user();
$complaintId = (int)($_GET['id'] ?? 0);

$allowedPriorities = [
    'Düşük',
    'Normal',
    'Yüksek',
    'Acil'
];

if ($complaintId <= 0) {
    flash('danger', 'Geçersiz başvuru numarası.');
    redirect('basvurular.php');
}

/*
|--------------------------------------------------------------------------
| Başvuruyu getir
|--------------------------------------------------------------------------
*/

$complaintStatement = $pdo->prepare("
    SELECT
        c.*,
        d.name AS department_name,
        assigned_user.full_name AS assigned_name
    FROM complaints AS c

    LEFT JOIN departments AS d
        ON d.id = c.department_id

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
| Atama işlemi
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $departmentId = (int)($_POST['department_id'] ?? 0);
    $assignedUserId = (int)($_POST['assigned_user_id'] ?? 0);
    $priority = trim($_POST['priority'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $assignmentDescription = trim(
        $_POST['assignment_description'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | Alan kontrolleri
    |--------------------------------------------------------------------------
    */

    if ($departmentId <= 0) {
        flash('danger', 'İlgili müdürlüğü seçmelisiniz.');
        redirect('personel_atama.php?id=' . $complaintId);
    }

    if ($assignedUserId <= 0) {
        flash('danger', 'Görevlendirilecek personeli seçmelisiniz.');
        redirect('personel_atama.php?id=' . $complaintId);
    }

    if (!in_array($priority, $allowedPriorities, true)) {
        flash('danger', 'Geçerli bir öncelik seviyesi seçmelisiniz.');
        redirect('personel_atama.php?id=' . $complaintId);
    }

    if ($dueDate === '') {
        flash('danger', 'Son işlem tarihini belirlemelisiniz.');
        redirect('personel_atama.php?id=' . $complaintId);
    }

    if (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        flash(
            'danger',
            'Son işlem tarihi bugünden önce olamaz.'
        );

        redirect('personel_atama.php?id=' . $complaintId);
    }

    if (mb_strlen($assignmentDescription) < 10) {
        flash(
            'danger',
            'Görev açıklaması en az 10 karakter olmalıdır.'
        );

        redirect('personel_atama.php?id=' . $complaintId);
    }

    /*
    |--------------------------------------------------------------------------
    | Müdürlük kontrolü
    |--------------------------------------------------------------------------
    */

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
        flash('danger', 'Seçilen müdürlük bulunamadı.');
        redirect('personel_atama.php?id=' . $complaintId);
    }

    /*
    |--------------------------------------------------------------------------
    | Personel kontrolü
    |--------------------------------------------------------------------------
    */

    $staffStatement = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            department_id
        FROM users
        WHERE id = ?
          AND role = 'staff'
          AND is_active = 1
        LIMIT 1
    ");

    $staffStatement->execute([$assignedUserId]);

    $assignedStaff = $staffStatement->fetch();

    if (!$assignedStaff) {
        flash('danger', 'Seçilen personel bulunamadı.');
        redirect('personel_atama.php?id=' . $complaintId);
    }

    if ((int)$assignedStaff['department_id'] !== $departmentId) {
        flash(
            'danger',
            'Seçilen personel bu müdürlüğe bağlı değildir.'
        );

        redirect('personel_atama.php?id=' . $complaintId);
    }

    /*
    |--------------------------------------------------------------------------
    | Kayıt işlemi
    |--------------------------------------------------------------------------
    */

    try {
        $pdo->beginTransaction();

        $updateStatement = $pdo->prepare("
            UPDATE complaints
            SET
                department_id = ?,
                assigned_user_id = ?,
                assignment_description = ?,
                assigned_by = ?,
                assigned_at = NOW(),
                priority = ?,
                due_date = ?,
                status = 'Yönlendirildi'
            WHERE id = ?
        ");

        $updateStatement->execute([
            $departmentId,
            $assignedUserId,
            $assignmentDescription,
            (int)$currentUser['id'],
            $priority,
            $dueDate,
            $complaintId
        ]);

        /*
        |--------------------------------------------------------------------------
        | Süreç geçmişi
        |--------------------------------------------------------------------------
        */

        $historyNote =
            'Başvuru ' .
            $department['name'] .
            ' birimine yönlendirildi. ' .
            $assignedStaff['full_name'] .
            ' isimli personele görev olarak atandı. ' .
            'Son işlem tarihi: ' .
            date('d.m.Y', strtotime($dueDate)) .
            '. Görev açıklaması: ' .
            $assignmentDescription;

        $historyStatement = $pdo->prepare("
            INSERT INTO complaint_history (
                complaint_id,
                user_id,
                status,
                note,
                is_public
            )
            VALUES (?, ?, 'Yönlendirildi', ?, 1)
        ");

        $historyStatement->execute([
            $complaintId,
            (int)$currentUser['id'],
            $historyNote
        ]);

        /*
        |--------------------------------------------------------------------------
        | Personele bildirim gönder
        |--------------------------------------------------------------------------
        */

        $notificationTitle = 'Yeni görev atandı';

        $notificationMessage =
            $complaint['tracking_code'] .
            ' takip numaralı "' .
            $complaint['title'] .
            '" başvurusu size atandı. ' .
            'Son işlem tarihi: ' .
            date('d.m.Y', strtotime($dueDate)) .
            '. Öncelik: ' .
            $priority .
            '.';

        $notificationStatement = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                complaint_id,
                title,
                message,
                notification_type
            )
            VALUES (?, ?, ?, ?, 'assignment')
        ");

        $notificationStatement->execute([
            $assignedUserId,
            $complaintId,
            $notificationTitle,
            $notificationMessage
        ]);

        $pdo->commit();

        flash(
            'success',
            'Talep personele atandı ve bildirim gönderildi.'
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash(
            'danger',
            'Personel atama işlemi sırasında hata oluştu.'
        );
    }

    redirect('basvuru_detay.php?id=' . $complaintId);
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

$staffList = $pdo->query("
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

$pageTitle = 'Personel Atama';

require '../includes/panel_header.php';
?>

<div class="detail-top">
    <div>
        <span class="text-muted">
            <?= e($complaint['tracking_code']) ?>
        </span>

        <h2>Personel Atama Modülü</h2>

        <p class="text-muted mb-0">
            <?= e($complaint['title']) ?>
        </p>
    </div>

    <a
        href="basvuru_detay.php?id=<?= $complaintId ?>"
        class="btn btn-outline-secondary"
    >
        <i class="fa-solid fa-arrow-left me-1"></i>
        Başvuruya Dön
    </a>
</div>

<div class="row g-4">

    <div class="col-xl-7">
        <div class="panel-card">

            <div class="panel-card-header">
                <div>
                    <h3>Görev ve Personel Bilgileri</h3>

                    <p>
                        Başvuruyu ilgili birime ve personele atayın
                    </p>
                </div>
            </div>

            <form method="post" class="row g-4">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <div class="col-12">
                    <label class="form-label">
                        İlgili Müdürlük / Birim *
                    </label>

                    <select
                        name="department_id"
                        id="departmentSelect"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Müdürlük seçiniz
                        </option>

                        <?php foreach ($departments as $department): ?>
                            <option
                                value="<?= (int)$department['id'] ?>"
                                <?= (int)$complaint['department_id']
                                    === (int)$department['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Görevlendirilecek Personel *
                    </label>

                    <select
                        name="assigned_user_id"
                        id="staffSelect"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Önce müdürlük seçiniz
                        </option>

                        <?php foreach ($staffList as $staff): ?>
                            <option
                                value="<?= (int)$staff['id'] ?>"
                                data-department="<?=
                                    (int)$staff['department_id']
                                ?>"
                                <?= (int)$complaint['assigned_user_id']
                                    === (int)$staff['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($staff['full_name']) ?>
                                — <?= e($staff['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        Yalnızca seçilen müdürlüğe bağlı personeller
                        listelenecektir.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Öncelik Seviyesi *
                    </label>

                    <select
                        name="priority"
                        class="form-select"
                        required
                    >
                        <?php foreach (
                            $allowedPriorities as $priority
                        ): ?>
                            <option
                                value="<?= e($priority) ?>"
                                <?= $complaint['priority'] === $priority
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($priority) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Son İşlem Tarihi *
                    </label>

                    <input
                        type="date"
                        name="due_date"
                        class="form-control"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= e(
                            $complaint['due_date']
                            ?: date(
                                'Y-m-d',
                                strtotime('+7 days')
                            )
                        ) ?>"
                        required
                    >
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Görev Açıklaması *
                    </label>

                    <textarea
                        name="assignment_description"
                        class="form-control"
                        rows="7"
                        placeholder="Personelin yapması gereken işlemleri ayrıntılı olarak yazınız..."
                        required
                    ><?= e(
                        $complaint['assignment_description'] ?? ''
                    ) ?></textarea>

                    <div class="form-text">
                        Konum kontrolü, saha incelemesi, yapılacak işlem
                        ve vatandaşa verilecek bilgi gibi ayrıntıları yazın.
                    </div>
                </div>

                <div class="col-12">
                    <div class="assignment-notification-info">
                        <i class="fa-solid fa-bell"></i>

                        <div>
                            <strong>
                                Personel bildirimi
                            </strong>

                            <span>
                                Atama kaydedildiğinde seçilen personelin
                                sağ üst bildirim alanına yeni görev
                                bildirimi gönderilecektir.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button
                        type="submit"
                        class="btn btn-primary btn-lg px-4"
                    >
                        <i class="fa-solid fa-user-check me-2"></i>
                        Görevi Ata ve Bildirim Gönder
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="panel-card">

            <div class="panel-card-header">
                <div>
                    <h3>Başvuru Özeti</h3>
                </div>
            </div>

            <div class="assignment-summary">

                <div>
                    <small>Takip Numarası</small>

                    <strong>
                        <?= e($complaint['tracking_code']) ?>
                    </strong>
                </div>

                <div>
                    <small>Başvuru Türü</small>

                    <strong>
                        <?= e($complaint['type']) ?>
                    </strong>
                </div>

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
                    <small>Başvuru Konusu</small>

                    <strong>
                        <?= e($complaint['title']) ?>
                    </strong>
                </div>

                <div>
                    <small>Mevcut Müdürlük</small>

                    <strong>
                        <?= e(
                            $complaint['department_name']
                            ?: 'Atanmamış'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Mevcut Personel</small>

                    <strong>
                        <?= e(
                            $complaint['assigned_name']
                            ?: 'Atanmamış'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <small>Mevcut Durum</small>

                    <span
                        class="badge text-bg-<?=
                            status_badge($complaint['status'])
                        ?>"
                    >
                        <?= e($complaint['status']) ?>
                    </span>
                </div>

            </div>

            <hr>

            <h5 class="fw-bold">
                Başvuru Açıklaması
            </h5>

            <p class="text-muted">
                <?= nl2br(e($complaint['description'])) ?>
            </p>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const departmentSelect =
        document.getElementById('departmentSelect');

    const staffSelect =
        document.getElementById('staffSelect');

    if (!departmentSelect || !staffSelect) {
        return;
    }

    function filterStaff() {
        const selectedDepartment =
            departmentSelect.value;

        let visibleStaffCount = 0;

        Array.from(staffSelect.options).forEach(
            function (option, index) {
                if (index === 0) {
                    return;
                }

                const staffDepartment =
                    option.dataset.department;

                const visible =
                    selectedDepartment !== '' &&
                    staffDepartment === selectedDepartment;

                option.hidden = !visible;
                option.disabled = !visible;

                if (visible) {
                    visibleStaffCount++;
                }

                if (!visible && option.selected) {
                    option.selected = false;
                }
            }
        );

        if (visibleStaffCount === 0) {
            staffSelect.value = '';

            staffSelect.options[0].textContent =
                selectedDepartment === ''
                    ? 'Önce müdürlük seçiniz'
                    : 'Bu müdürlüğe kayıtlı personel bulunmuyor';
        } else {
            staffSelect.options[0].textContent =
                'Personel seçiniz';
        }
    }

    departmentSelect.addEventListener(
        'change',
        filterStaff
    );

    filterStaff();
});
</script>

<?php require '../includes/panel_footer.php'; ?>