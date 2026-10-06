<!doctype html>
<html lang="de-AT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($pageTitle ?? '') !== '' ? $pageTitle . ' · ' : '') ?>X-Solution CRM</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="guest">
<main class="guest-wrap">
    <div class="guest-brand">
        <span class="logo-mark"><?= icon('logo') ?></span>
        <span class="logo-text">X-Solution</span>
    </div>
    <?php foreach (App\Core\Session::takeFlash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
    <p class="guest-foot">X-Solution CRM · <?= date('Y') ?></p>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
