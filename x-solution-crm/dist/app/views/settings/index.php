<?php
$mask = fn (string $v) => $v === '' ? 'nicht gesetzt' : str_repeat('•', 8) . substr($v, -4);
?>
<div class="page-head">
    <div>
        <div class="crumbs">System</div>
        <h1>Einstellungen</h1>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><h2>Mein Konto</h2></div>
        <dl class="details">
            <dt>Name</dt><dd><?= e($user['name']) ?></dd>
            <dt>E-Mail</dt><dd><?= e($user['email']) ?></dd>
            <dt>Rolle</dt><dd><?= $user['role'] === 'admin' ? 'Administrator' : 'Benutzer' ?></dd>
            <dt>Letzte Anmeldung</dt><dd><?= datetime_de($user['last_login_at']) ?></dd>
        </dl>
        <h3 class="mt-14">Passwort ändern</h3>
        <form method="post" action="<?= e(url('/einstellungen/passwort')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <label class="span-2">Aktuelles Passwort<input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>Neues Passwort<input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
            <label>Wiederholen<input type="password" name="new_password_confirm" required minlength="10" autocomplete="new-password"></label>
            <div class="span-2 form-actions"><button class="btn btn-primary" type="submit">Passwort ändern</button></div>
        </form>
    </section>

<?php if ($isAdmin): ?>
    <section class="card" id="easybill">
        <div class="card-head"><h2>easybill-Anbindung</h2><?= $apiKey !== '' ? badge('aktiv', 'API-Key hinterlegt') : badge('entwurf', 'Kein API-Key') ?></div>
        <?php if ($usingMock): ?>
            <div class="alert alert-warn">EASYBILL_BASE_URL ist gesetzt – es wird nicht die echte easybill-API verwendet (Testbetrieb mit Mock).</div>
        <?php endif; ?>
        <?php if (!$envWritable): ?>
            <div class="alert alert-info">Die .env ist nicht beschreibbar. Änderungen bitte direkt in der .env im Domain-Ordner vornehmen.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/einstellungen/easybill')) ?>" class="form-grid" autocomplete="off">
            <?= csrf_field() ?>
            <label class="span-2">easybill-API-Key <span class="field-hint">aktuell: <?= e($mask($apiKey)) ?> · in easybill unter Einstellungen → API erzeugen</span>
                <input type="password" name="api_key" placeholder="Neuen Key eintragen (leer = unverändert)" autocomplete="new-password">
            </label>
            <?php if ($apiKey !== ''): ?><label class="check span-2"><input type="checkbox" name="remove_api_key" value="1"> API-Key entfernen</label><?php endif; ?>
            <label class="span-2">Webhook-Secret
                <input name="webhook_secret" value="<?= e($webhookSecret) ?>" autocomplete="off">
            </label>
            <label class="check"><input type="checkbox" name="regenerate_webhook" value="1"> Webhook-Secret neu erzeugen</label>
            <label class="check"><input type="checkbox" name="regenerate_cron" value="1"> Cron-Secret neu erzeugen</label>
            <div class="span-2 form-actions">
                <button class="btn btn-primary" type="submit" <?= $envWritable ? '' : 'disabled' ?>>In .env speichern</button>
            </div>
        </form>
        <form method="post" action="<?= e(url('/einstellungen/easybill/test')) ?>" class="form-actions mt-14">
            <?= csrf_field() ?>
            <button class="btn" type="submit" <?= $apiKey === '' ? 'disabled' : '' ?>><?= icon('link') ?> Verbindung testen</button>
        </form>
        <h3 class="mt-14">Webhook-URL (in easybill eintragen)</h3>
        <pre class="code" data-copy title="Klicken zum Kopieren"><?= e($appUrl . '/easybill-webhook.php?secret=' . $webhookSecret) ?></pre>
        <h3>Cronjob-URL (alle 15 Minuten aufrufen)</h3>
        <pre class="code" data-copy title="Klicken zum Kopieren"><?= e($appUrl . '/cron.php?secret=' . $cronSecret) ?></pre>
        <p class="muted small">Letzter Abgleich: <?= $lastSync ? datetime_de($lastSync) : 'noch nie' ?> · Letzter Cron-Lauf: <?= $lastCron ? datetime_de($lastCron) . ' (' . e($lastCronStatus === 'ok' ? 'erfolgreich' : 'mit Fehlern') . ')' : 'noch nie' ?></p>
    </section>
</div>

<section class="card mb-20" id="erscheinungsbild">
    <div class="card-head"><div><h2>Erscheinungsbild</h2><span class="sub">Logo und Name in der Seitenleiste, auf der Anmeldeseite und als Browser-Icon</span></div></div>
    <?php $brand = App\Services\Branding::get(); ?>
    <div class="brand-preview"><?= App\Services\Branding::html() ?></div>
    <form method="post" action="<?= e(url('/einstellungen/branding')) ?>" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <label>Name neben dem Logo <span class="field-hint">leer lassen = nur Logo anzeigen</span>
            <input name="brand_name" maxlength="40" value="<?= e($brand['name']) ?>">
        </label>
        <label>Neues Logo <span class="field-hint">PNG, JPG, WebP oder SVG · max. 1 MB · am besten quadratisch oder quer, transparenter Hintergrund</span>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        </label>
        <?php if ($brand['logo']): ?><label class="check span-2"><input type="checkbox" name="remove_logo" value="1"> Eigenes Logo entfernen (Standard-Logo verwenden)</label><?php endif; ?>
        <div class="span-2 form-actions"><button class="btn btn-primary" type="submit">Speichern</button></div>
    </form>
</section>

<section class="card mb-20" id="benutzer">
    <div class="card-head"><h2>Benutzer</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Rolle</th><th>Status</th><th class="table-hide-sm">Letzte Anmeldung</th><th class="right">Aktionen</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; ?>
            <tr>
                <td><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="muted">(du)</span>' : '' ?><span class="cell-sub"><?= e($u['email']) ?></span></td>
                <td>
                    <form method="post" action="<?= e(url('/einstellungen/benutzer/' . $u['id'])) ?>" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="role">
                        <select name="role" data-autosubmit aria-label="Rolle">
                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Benutzer</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </form>
                </td>
                <td><?= $u['active'] ? badge('aktiv', 'Aktiv') : badge('inaktiv', 'Deaktiviert') ?></td>
                <td class="table-hide-sm"><?= datetime_de($u['last_login_at']) ?></td>
                <td class="right">
                    <div class="actions actions-end">
                        <form method="post" action="<?= e(url('/einstellungen/benutzer/' . $u['id'])) ?>" class="inline-form" data-confirm="Neues Passwort setzen?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="password">
                            <input type="password" name="password" placeholder="Neues Passwort" minlength="10" required class="pw-inline" autocomplete="new-password">
                            <button class="btn btn-sm" type="submit">Setzen</button>
                        </form>
                        <?php if (!$self): ?>
                        <form method="post" action="<?= e(url('/einstellungen/benutzer/' . $u['id'])) ?>" class="inline-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
                            <button class="btn btn-sm" type="submit"><?= $u['active'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/einstellungen/benutzer/' . $u['id'] . '/loeschen')) ?>" class="inline-form" data-confirm="Benutzer „<?= e($u['name']) ?>“ löschen?">
                            <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit" aria-label="Löschen"><?= icon('trash') ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <h3 class="mt-14">Neuen Benutzer anlegen</h3>
    <form method="post" action="<?= e(url('/einstellungen/benutzer')) ?>" class="form-grid cols-3" autocomplete="off">
        <?= csrf_field() ?>
        <label>Name<input name="name" required maxlength="120"></label>
        <label>E-Mail<input type="email" name="email" required></label>
        <label>Rolle<select name="role"><option value="user">Benutzer</option><option value="admin">Administrator</option></select></label>
        <label class="span-2">Startpasswort (min. 10 Zeichen)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('user-plus') ?> Anlegen</button></div>
    </form>
</section>

<section class="card">
    <div class="card-head"><div><h2>Synchronisationsprotokoll</h2><span class="sub">Letzte 25 API-Aufrufe, Webhooks und Cron-Läufe</span></div></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Zeit</th><th>Kanal</th><th>Aufruf</th><th>Status</th><th>Meldung</th><th class="right table-hide-sm">Dauer</th></tr></thead>
        <tbody>
        <?php if (!$log): ?><tr><td colspan="6" class="muted">Noch keine Einträge.</td></tr><?php endif; ?>
        <?php foreach ($log as $l): ?>
            <tr>
                <td class="nowrap"><?= datetime_de($l['created_at']) ?></td>
                <td><?= e(['api' => 'API', 'webhook' => 'Webhook', 'cron' => 'Cron', 'system' => 'System'][$l['channel']] ?? $l['channel']) ?></td>
                <td class="small"><?= e(trim(($l['method'] ?? '') . ' ' . ($l['endpoint'] ?? ''))) ?><?= $l['http_status'] ? ' <span class="muted">(' . (int) $l['http_status'] . ')</span>' : '' ?></td>
                <td><?= badge(['ok' => 'aktiv', 'verarbeitet' => 'aktiv', 'empfangen' => 'offen', 'fehler' => 'auslaufend', 'ignoriert' => 'beendet'][$l['status']] ?? 'beendet', ucfirst($l['status'])) ?></td>
                <td class="small"><?= e($l['message'] ?? '') ?></td>
                <td class="right nowrap table-hide-sm"><?= $l['duration_ms'] !== null ? (int) $l['duration_ms'] . ' ms' : '–' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php else: ?>
    <section class="card">
        <div class="card-head"><h2>easybill &amp; Benutzer</h2></div>
        <p class="muted">Diese Einstellungen können nur Administratoren ändern.</p>
    </section>
</div>
<?php endif; ?>
