<div class="page-head">
    <div>
        <div class="crumbs">Organisation</div>
        <h1>Aufgaben &amp; Termine</h1>
    </div>
    <div class="actions">
        <a class="btn <?= $mine ? '' : 'btn-primary' ?>" href="<?= e(url('/aufgaben', ['tab' => $tab, 'meine' => $mine ? '0' : '1'])) ?>"><?= icon('users') ?> <?= $mine ? 'Alle Benutzer anzeigen' : 'Nur meine anzeigen' ?></a>
    </div>
</div>

<div class="grid grid-2-1">
    <section class="card">
        <div class="tabs">
            <?php foreach (['offen' => 'Offen', 'heute' => 'Heute', 'woche' => 'Nächste 7 Tage', 'ueberfaellig' => 'Überfällig', 'erledigt' => 'Erledigt'] as $k => $l): ?>
                <a href="<?= e(url('/aufgaben', ['tab' => $k, 'meine' => $mine ? '1' : '0'])) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= $l ?><span class="count"><?= (int) $counts[$k] ?></span></a>
            <?php endforeach; ?>
        </div>
        <?= App\Core\View::partial('partials/tasks', ['tasks' => $tasks, 'showCustomer' => true]) ?>
    </section>

    <section class="card">
        <div class="card-head"><h2>Neu anlegen</h2></div>
        <form method="post" action="<?= e(url('/aufgaben')) ?>" class="stack-sm">
            <?= csrf_field() ?>
            <label>Art
                <select name="kind">
                    <option value="aufgabe">Aufgabe</option>
                    <option value="termin">Termin</option>
                </select>
            </label>
            <label>Titel *<input name="title" required maxlength="190"></label>
            <div class="form-grid">
                <label>Datum<input name="due_date" data-date placeholder="TT.MM.JJJJ" value="<?= date('d.m.Y', strtotime('+1 day')) ?>"></label>
                <label>Uhrzeit<input name="due_time" placeholder="HH:MM" value="09:00"></label>
            </div>
            <label>Kunde
                <select name="customer_id">
                    <option value="">– ohne Kunde –</option>
                    <?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Zuständig
                <select name="assigned_to">
                    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === App\Core\Auth::id() ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Beschreibung<textarea name="description" maxlength="5000"></textarea></label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('plus') ?> Speichern</button></div>
        </form>
    </section>
</div>
