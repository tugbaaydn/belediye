<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$departments = $pdo->query("
    SELECT id, name
    FROM departments
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();

$departmentIds = array_map('intval', array_column($departments, 'id'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $citizenName = trim($_POST['citizen_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $type = $_POST['type'] ?? '';
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    if ($citizenName === '') {
        $errors[] = 'Ad soyad alanı zorunludur.';
    }

    if ($phone === '') {
        $errors[] = 'Telefon alanı zorunludur.';
    }

    if (!in_array($type, ['Talep', 'Öneri', 'Şikâyet'], true)) {
        $errors[] = 'Geçerli bir başvuru türü seçiniz.';
    }

    if (!in_array($departmentId, $departmentIds, true)) {
        $errors[] = 'Başvurunun ilgili olduğu müdürlüğü seçiniz.';
    }

    if (mb_strlen($title) < 5) {
        $errors[] = 'Konu en az 5 karakter olmalıdır.';
    }

    if (mb_strlen($description) < 20) {
        $errors[] = 'Açıklama en az 20 karakter olmalıdır.';
    }

    if ($district === '') {
        $errors[] = 'Mahalle bilgisi zorunludur.';
    }

    if ($address === '') {
        $errors[] = 'Adres veya konum açıklaması zorunludur.';
    }

    $latitudeValid =
        filter_var($latitude, FILTER_VALIDATE_FLOAT) !== false &&
        (float)$latitude >= -90 &&
        (float)$latitude <= 90;

    $longitudeValid =
        filter_var($longitude, FILTER_VALIDATE_FLOAT) !== false &&
        (float)$longitude >= -180 &&
        (float)$longitude <= 180;

    if (!$latitudeValid || !$longitudeValid) {
        $errors[] = 'Konumumu Al butonuna basarak konumunuzu eklemelisiniz.';
    }

    $attachmentName = null;

    if (empty($_FILES['attachment']['name'])) {
        $errors[] = 'Başvuruya fotoğraf eklemek zorunludur.';
    } elseif (
        ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE)
        !== UPLOAD_ERR_OK
    ) {
        $errors[] = 'Fotoğraf yüklenemedi.';
    } else {
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $mimeType = mime_content_type(
            $_FILES['attachment']['tmp_name']
        );

        if (!isset($allowedTypes[$mimeType])) {
            $errors[] = 'Fotoğraf JPG, PNG veya WEBP olmalıdır.';
        } elseif ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Fotoğraf en fazla 5 MB olabilir.';
        } else {
            $attachmentName =
                bin2hex(random_bytes(12)) .
                '.' .
                $allowedTypes[$mimeType];
        }
    }

    if (!$errors && $attachmentName) {
        $uploadPath = __DIR__ . '/uploads/' . $attachmentName;

        if (!move_uploaded_file(
            $_FILES['attachment']['tmp_name'],
            $uploadPath
        )) {
            $errors[] =
                'Fotoğraf kaydedilemedi. uploads klasörünü kontrol ediniz.';
        }
    }

    if (!$errors) {
        $analysis = smart_analysis(
            $title,
            $description,
            $departments
        );

        $trackingCode = generate_tracking_code($pdo);
        $dueDate = due_date_for_priority(
            $analysis['priority']
        );

        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                tracking_code,
                citizen_name,
                phone,
                email,
                type,
                title,
                description,
                address,
                district,
                latitude,
                longitude,
                attachment,
                department_id,
                suggested_department_id,
                priority,
                status,
                due_date
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Yeni', ?
            )
        ");

        $stmt->execute([
            $trackingCode,
            $citizenName,
            $phone,
            $email ?: null,
            $type,
            $title,
            $description,
            $address,
            $district,
            (float)$latitude,
            (float)$longitude,
            $attachmentName,
            $departmentId,
            $analysis['department_id'],
            $analysis['priority'],
            $dueDate
        ]);

        $selectedDepartmentName = '';

        foreach ($departments as $department) {
            if ((int)$department['id'] === $departmentId) {
                $selectedDepartmentName = $department['name'];
                break;
            }
        }

        $complaintId = (int)$pdo->lastInsertId();

        $historyNote =
            'Başvuru vatandaş tarafından fotoğraf ve konum bilgisiyle oluşturuldu. ' .
            'Vatandaşın seçtiği birim: ' .
            $selectedDepartmentName .
            '.';

        if (
            $analysis['department_name'] &&
            (int)$analysis['department_id'] !== $departmentId
        ) {
            $historyNote .=
                ' Akıllı sistemin alternatif önerisi: ' .
                $analysis['department_name'] .
                '.';
        }

        $historyStmt = $pdo->prepare("
            INSERT INTO complaint_history (
                complaint_id,
                status,
                note,
                is_public
            )
            VALUES (?, 'Yeni', ?, 1)
        ");

        $historyStmt->execute([
            $complaintId,
            $historyNote
        ]);

        start_session();

        $_SESSION['created_tracking_code'] = $trackingCode;

        redirect('basvuru-basarili.php');
    }
}

$pageTitle = 'Yeni Başvuru Oluştur';
$basePath = '';

require 'includes/header.php';
require 'includes/public_nav.php';
?>

<section class="page-hero">
    <div class="container">
        <span>Vatandaş Başvuru Merkezi</span>

        <h1>Talep, öneri veya şikâyetinizi iletin</h1>

        <p>
            Müdürlük, fotoğraf ve konum bilgileri
            başvurunun daha hızlı değerlendirilmesini sağlar.
        </p>
    </div>
</section>

<section class="form-section">
    <div class="container">
        <div class="form-shell">

            <div class="required-info">
                <i class="fa-solid fa-circle-info"></i>

                <span>
                    İlgili müdürlük, açık adres, cihaz konumu
                    ve fotoğraf alanları zorunludur.
                </span>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <strong>
                        Lütfen aşağıdaki alanları kontrol ediniz:
                    </strong>

                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                method="post"
                enctype="multipart/form-data"
                class="row g-4"
                id="complaintForm"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <div class="col-12">
                    <h4 class="form-title">
                        <span>1</span>
                        İletişim Bilgileri
                    </h4>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Ad Soyad *
                    </label>

                    <input
                        type="text"
                        name="citizen_name"
                        class="form-control"
                        value="<?= e($_POST['citizen_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Telefon *
                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?= e($_POST['phone'] ?? '') ?>"
                        placeholder="05xx xxx xx xx"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        E-posta
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= e($_POST['email'] ?? '') ?>"
                    >
                </div>

                <div class="col-12">
                    <h4 class="form-title">
                        <span>2</span>
                        Başvuru Bilgileri
                    </h4>
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Başvuru Türü *
                    </label>

                    <select
                        name="type"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Seçiniz
                        </option>

                        <?php foreach (
                            ['Talep', 'Öneri', 'Şikâyet'] as $type
                        ): ?>
                            <option
                                value="<?= e($type) ?>"
                                <?= ($_POST['type'] ?? '') === $type
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">
                        İlgili Müdürlük / Birim *
                    </label>

                    <select
                        name="department_id"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Başvurunun ilgili olduğu birimi seçiniz
                        </option>

                        <?php foreach ($departments as $department): ?>
                            <option
                                value="<?= (int)$department['id'] ?>"
                                <?= (int)($_POST['department_id'] ?? 0)
                                    === (int)$department['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($department['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        Belediye personeli gerekli görürse
                        başvuruyu başka birime aktarabilir.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Konu *
                    </label>

                    <input
                        type="text"
                        name="title"
                        class="form-control"
                        value="<?= e($_POST['title'] ?? '') ?>"
                        placeholder="Örneğin: Mahalle yolunda çukur var"
                        required
                    >
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Açıklama *
                    </label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="6"
                        placeholder="Sorunu ayrıntılı şekilde açıklayınız..."
                        required
                    ><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <h4 class="form-title">
                        <span>3</span>
                        Konum Bilgileri
                    </h4>
                </div>

                <div class="col-md-5">
                    <label class="form-label">
                        Mahalle *
                    </label>

                    <input
                        type="text"
                        name="district"
                        class="form-control"
                        value="<?= e($_POST['district'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="col-md-7">
                    <label class="form-label">
                        Adres / Konum Açıklaması *
                    </label>

                    <input
                        type="text"
                        name="address"
                        class="form-control"
                        value="<?= e($_POST['address'] ?? '') ?>"
                        placeholder="Cadde, sokak, bina veya yakın konum"
                        required
                    >
                </div>

                <div class="col-12">
                    <div class="location-box">
                        <div>
                            <strong>
                                <i class="fa-solid fa-location-dot me-2"></i>
                                Cihaz Konumu *
                            </strong>

                            <p id="locationStatus">
                                Konum henüz eklenmedi.
                                Başvuru göndermek için konumunuzu alınız.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            id="getLocationButton"
                        >
                            <i class="fa-solid fa-crosshairs me-2"></i>
                            Konumumu Al
                        </button>
                    </div>

                    <input
                        type="hidden"
                        name="latitude"
                        id="latitude"
                        value="<?= e($_POST['latitude'] ?? '') ?>"
                    >

                    <input
                        type="hidden"
                        name="longitude"
                        id="longitude"
                        value="<?= e($_POST['longitude'] ?? '') ?>"
                    >
                </div>

                <div class="col-12">
                    <h4 class="form-title">
                        <span>4</span>
                        Fotoğraf
                    </h4>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Sorunu Gösteren Fotoğraf *
                    </label>

                    <input
                        type="file"
                        name="attachment"
                        id="attachment"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                        required
                    >

                    <div class="form-text">
                        JPG, PNG veya WEBP formatında,
                        en fazla 5 MB fotoğraf yükleyebilirsiniz.
                    </div>

                    <div
                        id="photoPreviewWrap"
                        class="photo-preview-wrap d-none"
                    >
                        <img
                            id="photoPreview"
                            alt="Fotoğraf önizlemesi"
                        >
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button
                        type="submit"
                        class="btn btn-primary btn-lg px-5"
                    >
                        <i class="fa-solid fa-paper-plane me-2"></i>
                        Başvuruyu Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>