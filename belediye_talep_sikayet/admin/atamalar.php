<?php

declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/auth.php';

require_role(['admin']);

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        c.id,
        c.tracking_code,
        c.title,
        c.type,
        c.status,
        c.priority,
        c.due_date,
        c.assigned_at,
        c.assignment_description,

        d.name AS department_name,
        u.full_name AS assigned_name

    FROM complaints AS c

    LEFT JOIN departments AS d
        ON d.id = c.department_id

    LEFT JOIN users AS u
        ON u.id = c.assigned_user_id

    WHERE c.status NOT IN ('Çözüldü', 'Reddedildi')
";

$params = [];

if ($statusFilter === 'unassigned') {
    $sql .= " AND c.assigned_user_id IS NULL";
}

if ($statusFilter === 'assigned') {
    $sql .= " AND c.assigned_user_id IS NOT NULL";
}

$sql .= "
    ORDER BY
        CASE c.priority
            WHEN 'Acil' THEN 1
            WHEN 'Yüksek' THEN 2
            WHEN 'Normal' THEN 3
            ELSE 4
        END,
        c.created_at DESC
";

$statement = $pdo->prepare($sql);
$statement->execute($params);

$complaints = $statement->fetchAll();

$pageTitle = 'Personel Atama Modülü';

require '../includes/panel_header.php';
?>

<div class="panel-card">

    <div class="panel-card-header">
        <div>
            <h3>Görev ve Personel Atamaları</h3>

            <p>
                Başvuruları birime ve personele yönlendirin
            </p>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a
            href="atamalar.php"
            class="btn <?= $statusFilter === ''
                ? 'btn-primary'
                : 'btn-outline-primary'
            ?>"
        >
            Tüm Aktif Başvurular
        </a>

        <a
            href="atamalar.php?status=unassigned"
            class="btn <?= $statusFilter === 'unassigned'
                ? 'btn-primary'
                : 'btn-outline-primary'
            ?>"
        >
            Personel Atanmayanlar
        </a>

        <a
            href="atamalar.php?status=assigned"
            class="btn <?= $statusFilter === 'assigned'
                ? 'btn-primary'
                : 'btn-outline-primary'
            ?>"
        >
            Atanan Görevler
        </a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">

            <thead>
                <tr>
                    <th>Başvuru</th>
                    <th>Müdürlük</th>
                    <th>Personel</th>
                    <th>Öncelik</th>
                    <th>Son Tarih</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($complaints as $complaint): ?>

                    <?php
                    $isOverdue =
                        !empty($complaint['due_date']) &&
                        strtotime($complaint['due_date']) <
                        strtotime(date('Y-m-d'));
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= e($complaint['tracking_code']) ?>
                            </strong>

                            <span class="table-title">
                                <?= e($complaint['title']) ?>
                            </span>

                            <small>
                                <?= e($complaint['type']) ?>
                            </small>
                        </td>

                        <td>
                            <?= e(
                                $complaint['department_name']
                                ?: 'Birim seçilmedi'
                            ) ?>
                        </td>

                        <td>
                            <?php if ($complaint['assigned_name']): ?>
                                <strong>
                                    <?= e($complaint['assigned_name']) ?>
                                </strong>

                                <?php if (
                                    $complaint['assignment_description']
                                ): ?>
                                    <small>
                                        <?= e(
                                            mb_strimwidth(
                                                $complaint[
                                                    'assignment_description'
                                                ],
                                                0,
                                                60,
                                                '...'
                                            )
                                        ) ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-danger">
                                    Personel atanmadı
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span
                                class="badge text-bg-<?=
                                    priority_badge(
                                        $complaint['priority']
                                    )
                                ?>"
                            >
                                <?= e($complaint['priority']) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($complaint['due_date']): ?>
                                <span
                                    class="<?= $isOverdue
                                        ? 'text-danger fw-bold'
                                        : ''
                                    ?>"
                                >
                                    <?= date(
                                        'd.m.Y',
                                        strtotime(
                                            $complaint['due_date']
                                        )
                                    ) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td>
                            <span
                                class="badge text-bg-<?=
                                    status_badge(
                                        $complaint['status']
                                    )
                                ?>"
                            >
                                <?= e($complaint['status']) ?>
                            </span>
                        </td>

                        <td>
                            <a
                                href="personel_atama.php?id=<?=
                                    (int)$complaint['id']
                                ?>"
                                class="btn btn-sm btn-primary"
                            >
                                <i class="fa-solid fa-user-check"></i>

                                <?= $complaint['assigned_name']
                                    ? 'Atamayı Güncelle'
                                    : 'Personel Ata'
                                ?>
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$complaints): ?>
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            Gösterilecek başvuru bulunamadı.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/panel_footer.php'; ?>