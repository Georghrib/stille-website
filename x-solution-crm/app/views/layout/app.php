<?php
use App\Core\Auth;
use App\Core\Session;
use App\Services\Notifications;
use App\Services\Period;

$user = Auth::user();
$firstName = explode(' ', trim((string) $user['name']))[0] ?? '';
$inboxCount = Notifications::inboxCount();
$taskCount = Notifications::openTaskCount();
$notifications = Notifications::items();
$period = Period::current();
$hour = (int) date('G');
$nav = [
    ['/', 'Dashboard', 'dashboard', 0],
    ['/kunden', 'Kunden', 'users', 0],
    ['/vertraege', 'Verträge', 'file', 0],
    ['/easybill', 'Aus easybill', 'inbox', $inboxCount],
    ['/aufgaben', 'Aufgaben', 'check', $taskCount],
    ['/umsatz', 'Umsatz', 'chart', 0],
    ['/einstellungen', 'Einstellungen', 'settings', 0],
];
?>
<!doctype html>
<html lang="de-AT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
    <title><?= e(($pageTitle ?? '') !== '' ? $pageTitle . ' · ' : '') ?><?= e(App\Services\Branding::name() ?: 'CRM') ?></title>
    <?= App\Services\Branding::faviconTag() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (!empty($useCharts)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
    <?php endif; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="logo" href="<?= e(url('/')) ?>">
            <?= App\Services\Branding::html() ?>
        </a>
        <nav class="nav" aria-label="Hauptnavigation">
            <?php foreach ($nav as [$href, $label, $ic, $count]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= is_active_path($href) ? 'active' : '' ?>">
                    <?= icon($ic) ?><span><?= e($label) ?></span>
                    <?php if ($count > 0): ?><span class="count"><?= (int) $count ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <span class="avatar"><?= e(initials((string) $user['name'])) ?></span>
            <div class="who">
                <strong><?= e($user['name']) ?></strong>
                <span><?= $user['role'] === 'admin' ? 'Administrator' : 'Benutzer' ?></span>
            </div>
            <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="Abmelden" aria-label="Abmelden"><?= icon('logout') ?></button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" type="button" data-toggle-nav aria-label="Menü öffnen"><?= icon('menu') ?></button>
            <div class="greeting">
                <h1>Willkommen zurück, <?= e($firstName) ?></h1>
                <p><?= $hour < 11 ? 'Guten Morgen' : ($hour < 18 ? 'Schönen Tag' : 'Guten Abend') ?> – heute ist <?= e(['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][(int) date('w')]) ?>, <?= date('d.m.Y') ?>.</p>
            </div>
            <div class="topbar-tools">
                <form method="get" action="" class="period-form">
                    <label class="sr-only" for="periode">Zeitraum</label>
                    <select id="periode" name="periode" data-autosubmit title="Bezugszeitraum">
                        <?php foreach (Period::options() as $ym => $label): ?>
                            <option value="<?= e($ym) ?>" <?= $ym === $period ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <form method="get" action="<?= e(url('/suche')) ?>" class="search" role="search">
                    <?= icon('search') ?>
                    <input type="search" name="q" placeholder="Kunden, Verträge, Belege …" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Suche">
                </form>
                <div class="bell">
                    <button class="icon-btn" type="button" data-dropdown="bell-menu" aria-label="Benachrichtigungen" aria-expanded="false">
                        <?= icon('bell') ?>
                        <?php if ($notifications): ?><span class="dot"><?= count($notifications) ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown hidden" id="bell-menu">
                        <h3>Benachrichtigungen</h3>
                        <?php if (!$notifications): ?>
                            <div class="empty">Alles erledigt – keine offenen Hinweise.</div>
                        <?php endif; ?>
                        <?php foreach ($notifications as $n): ?>
                            <a href="<?= e($n['url']) ?>">
                                <span class="urgency <?= e($n['tone']) ?>"></span>
                                <span><strong><?= e($n['title']) ?></strong><span class="cell-sub"><?= e($n['text']) ?></span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </header>

        <?php $flashes = Session::takeFlash(); if ($flashes): ?>
            <div class="flash-stack">
                <?php foreach ($flashes as $f): ?>
                    <div class="alert alert-<?= e($f['type']) ?>" data-dismiss><?= e($f['message']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </div>
</div>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
</body>
</html>
