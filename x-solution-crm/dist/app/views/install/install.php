<?php
/** @var string $step */
$errors ??= [];
?>
<section class="card guest-card guest-card-wide">
<?php if ($step === 'locked'): ?>
    <h1>Installation gesperrt</h1>
    <p class="muted">X-Solution CRM ist bereits installiert. Aus Sicherheitsgründen kann die Installation nicht erneut ausgeführt werden.</p>
    <div class="alert alert-warn">Bitte lösche die Datei <code>public_html/install.php</code> jetzt über den Dateimanager bzw. per FTP.</div>
    <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Zur Anmeldung</a>

<?php elseif ($step === 'done'): ?>
    <h1>Installation abgeschlossen</h1>
    <p class="muted">Die Tabellen wurden angelegt und dein Administrator-Konto ist eingerichtet<?= $demo ? ', Demodaten wurden eingespielt' : '' ?>.</p>
    <?php if ($locked): ?>
        <div class="alert alert-warn"><strong>Wichtig:</strong> Die Installation hat sich selbst gesperrt. Lösche jetzt bitte trotzdem die Datei <code>public_html/install.php</code>.</div>
    <?php else: ?>
        <div class="alert alert-danger">Die Sperrdatei <code>storage/install.lock</code> konnte nicht geschrieben werden. Lösche <code>public_html/install.php</code> unbedingt sofort!</div>
    <?php endif; ?>
    <h2>Cronjob (alle 15 Minuten)</h2>
    <p class="muted small">Im Hosting-Panel unter „Cronjobs“ eintragen oder bei einem externen Ping-Dienst hinterlegen:</p>
    <pre class="code" data-copy><?= e($cronUrl) ?></pre>
    <h2>easybill-Webhook-URL</h2>
    <p class="muted small">In easybill unter Einstellungen → Webhooks als Ziel-URL eintragen (Ereignisse: Dokument erstellt/geändert):</p>
    <pre class="code" data-copy><?= e($webhookUrl) ?></pre>
    <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Zur Anmeldung</a>

<?php elseif ($step === 'env'): ?>
    <h1>Installation · Schritt 1 von 2</h1>
    <p class="muted">Es wurde noch keine <code>.env</code> gefunden. Trage die Datenbank-Zugangsdaten aus dem Hosting-Panel ein – die Datei wird außerhalb von public_html angelegt. Webhook- und Cron-Secret werden automatisch erzeugt.</p>
    <?= App\Core\View::partial('install/requirements', ['requirements' => $requirements]) ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($envContent !== null): ?>
        <div class="alert alert-warn">Die Datei <code>.env</code> konnte nicht geschrieben werden. Lege sie bitte im Dateimanager im Domain-Ordner (neben <code>public_html</code>) mit folgendem Inhalt an und lade diese Seite neu:</div>
        <pre class="code" data-copy><?= e($envContent) ?></pre>
    <?php endif; ?>
    <form method="post" class="form-grid" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="env">
        <label>Datenbank-Host<input name="DB_HOST" value="<?= e($form['DB_HOST']) ?>" required></label>
        <label>Datenbankname<input name="DB_NAME" value="<?= e($form['DB_NAME']) ?>" required placeholder="u123456789_crm"></label>
        <label>Datenbank-Benutzer<input name="DB_USER" value="<?= e($form['DB_USER']) ?>" required placeholder="u123456789_crm"></label>
        <label>Datenbank-Passwort<input type="password" name="DB_PASS" value=""></label>
        <label class="span-2">Adresse der Anwendung (APP_URL)<input name="APP_URL" value="<?= e($form['APP_URL']) ?>" required></label>
        <div class="span-2 form-actions"><button class="btn btn-primary" type="submit">Verbindung prüfen &amp; .env anlegen</button></div>
    </form>

<?php else: ?>
    <h1>Installation · Schritt 2 von 2</h1>
    <p class="muted">Die Tabellen werden angelegt und dein erstes Administrator-Konto erstellt.</p>
    <?= App\Core\View::partial('install/requirements', ['requirements' => $requirements]) ?>
    <?php if ($dbError): ?>
        <div class="alert alert-danger">Keine Verbindung zur Datenbank: <?= e($dbError) ?><br>Bitte die Werte DB_HOST, DB_NAME, DB_USER und DB_PASS in der <code>.env</code> prüfen.</div>
    <?php endif; ?>
    <?php if ($missingSecrets): ?>
        <div class="alert alert-warn">In der .env fehlen noch: <?= e(implode(', ', $missingSecrets)) ?>. Webhook und Cronjob funktionieren erst, wenn diese Werte gesetzt sind.</div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form-grid" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="install">
        <label>Dein Name<input name="name" value="<?= e($form['name']) ?>" required maxlength="120"></label>
        <label>E-Mail (Login)<input type="email" name="email" value="<?= e($form['email']) ?>" required></label>
        <label>Passwort (min. 10 Zeichen)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label>Passwort wiederholen<input type="password" name="password_confirm" required minlength="10" autocomplete="new-password"></label>
        <label class="check span-2"><input type="checkbox" name="demo" value="1" <?= $form['demo'] ? 'checked' : '' ?>> Demodaten anlegen (Beispielkunden, Verträge, Rechnungen – empfohlen zum Ausprobieren)</label>
        <div class="span-2 form-actions"><button class="btn btn-primary" type="submit" <?= $dbError ? 'disabled' : '' ?>>Jetzt installieren</button></div>
    </form>
<?php endif; ?>
</section>
