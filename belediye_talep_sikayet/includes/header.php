<?php
require_once __DIR__ . '/functions.php';
start_session();
$pageTitle = $pageTitle ?? 'Belediye Talep Yönetimi';
$basePath = $basePath ?? '';
$flash = get_flash();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e($basePath) ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if ($flash): ?>
<div class="position-fixed top-0 end-0 p-3 toast-container-custom">
    <div class="alert alert-<?= e($flash['type']) ?> shadow" role="alert">
        <?= e($flash['message']) ?>
    </div>
</div>
<?php endif; ?>
