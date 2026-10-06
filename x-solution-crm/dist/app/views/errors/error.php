<section class="card error-card">
    <div class="error-code"><?= e($code) ?></div>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Zum Dashboard</a>
</section>
