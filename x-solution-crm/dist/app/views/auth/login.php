<section class="card guest-card">
    <h1>Anmelden</h1>
    <p class="muted">Melde dich mit deinem X-Solution-Konto an.</p>
    <form method="post" action="<?= e(url('/login')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <label>E-Mail-Adresse
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
        </label>
        <label>Passwort
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary" type="submit">Anmelden</button>
    </form>
</section>
<?php unset($_SESSION['_old']); ?>
