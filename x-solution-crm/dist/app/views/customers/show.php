<?php
use App\Core\View;
use App\Services\ContractService;
$c = $customer;
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('/kunden')) ?>">Kunden</a> / <?= e($c['name']) ?></div>
        <h1><?= e($c['name']) ?> <?= badge($c['status'], customer_status_label($c['status'])) ?></h1>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('/kunden/' . $c['id'] . '/bearbeiten')) ?>"><?= icon('edit') ?> Bearbeiten</a>
        <a class="btn btn-primary" href="<?= e(url('/vertraege/neu', ['kunde' => $c['id']])) ?>"><?= icon('plus') ?> Neuer Vertrag</a>
    </div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-label">Umsatz gesamt (netto)</div><div class="kpi-value"><?= money($stats['revenue']) ?></div><span class="kpi-icon"><?= icon('euro') ?></span></div>
    <div class="card kpi"><div class="kpi-label">Vertragsumsatz p.&nbsp;a.</div><div class="kpi-value"><?= money($annual) ?></div><span class="kpi-icon"><?= icon('trend-up') ?></span></div>
    <div class="card kpi"><div class="kpi-label">Offene Rechnungen (brutto)</div><div class="kpi-value"><?= money($stats['open_amount']) ?></div><span class="kpi-icon"><?= icon('clock') ?></span></div>
    <div class="card kpi"><div class="kpi-label">Offene Angebote</div><div class="kpi-value"><?= money($stats['offers']) ?></div><span class="kpi-icon"><?= icon('target') ?></span></div>
</div>

<div class="grid grid-2-1">
    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Verträge</h2><a class="link-more" href="<?= e(url('/vertraege/neu', ['kunde' => $c['id']])) ?>"><?= icon('plus') ?> Vertrag anlegen</a></div>
            <?php if (!$contracts): ?>
                <div class="empty-state"><?= icon('file') ?><p>Noch keine Verträge.</p></div>
            <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Vertrag</th><th>Status</th><th>Start</th><th>Ende</th><th class="right">Umsatz p.&nbsp;a.</th></tr></thead>
                <tbody>
                <?php foreach ($contracts as $k): ?>
                    <tr>
                        <td><a class="row-link" href="<?= e(url('/vertraege/' . $k['id'])) ?>"><?= e($k['title']) ?></a><span class="cell-sub"><?= e(interval_label($k['billing_interval'])) ?></span></td>
                        <td><?= ContractService::stateBadge($k) ?></td>
                        <td class="nowrap"><?= date_de($k['start_date']) ?></td>
                        <td class="nowrap"><?= date_de($k['end_date']) ?></td>
                        <td class="right nowrap"><?= $k['billing_interval'] === 'einmalig' ? money($k['total_value_cents']) . '<span class="cell-sub">einmalig</span>' : money(ContractService::annualValue($k)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>easybill-Belege</h2><span class="sub"><?= count($documents) ?> Belege</span></div>
            <?= View::partial('partials/documents', ['documents' => $documents, 'showCustomer' => false]) ?>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Stammdaten</h2></div>
            <dl class="details">
                <dt>Ansprechpartner</dt><dd><?= e($c['contact_person'] ?: '–') ?></dd>
                <dt>E-Mail</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '–' ?></dd>
                <dt>Telefon</dt><dd><?= $c['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . e($c['phone']) . '</a>' : '–' ?></dd>
                <dt>Adresse</dt><dd><?= e(trim(($c['street'] ?? '') . ', ' . ($c['zip'] ?? '') . ' ' . ($c['city'] ?? ''), ', ') ?: '–') ?> <?= $c['country'] !== 'AT' ? e($c['country']) : '' ?></dd>
                <dt>UID</dt><dd><?= e($c['vat_id'] ?: '–') ?></dd>
                <dt>easybill-ID</dt><dd><?= e($c['easybill_customer_id'] ?? '–') ?></dd>
                <dt>Angelegt</dt><dd><?= date_de($c['created_at']) ?></dd>
            </dl>
        </section>

        <section class="card">
            <div class="card-head"><h2>Notizen</h2></div>
            <form method="post" action="<?= e(url('/kunden/' . $c['id'] . '/notizen')) ?>" class="stack-sm">
                <?= csrf_field() ?>
                <textarea name="body" required placeholder="Gesprächsnotiz, Vereinbarung, Hinweis …" maxlength="5000"></textarea>
                <?php if ($contracts): ?>
                <select name="contract_id">
                    <option value="">Kein bestimmter Vertrag</option>
                    <?php foreach ($contracts as $k): ?><option value="<?= (int) $k['id'] ?>"><?= e($k['title']) ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div class="form-actions"><button class="btn btn-primary btn-sm" type="submit"><?= icon('note') ?> Notiz speichern</button></div>
            </form>
            <?= View::partial('partials/notes', ['notes' => $notes]) ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Aufgaben &amp; Termine</h2><a class="link-more" href="<?= e(url('/aufgaben')) ?>">Alle <?= icon('chevron-right') ?></a></div>
            <?= View::partial('partials/task_form', ['customerId' => $c['id'], 'contractId' => null, 'users' => $users, 'compact' => true]) ?>
            <?= View::partial('partials/tasks', ['tasks' => $tasks, 'showCustomer' => false]) ?>
        </section>

        <?php if (App\Core\Auth::isAdmin()): ?>
        <form method="post" action="<?= e(url('/kunden/' . $c['id'] . '/loeschen')) ?>" data-confirm="Kunde „<?= e($c['name']) ?>“ samt Verträgen, Notizen und Terminen endgültig löschen? easybill-Belege bleiben erhalten.">
            <?= csrf_field() ?>
            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Kunde löschen</button>
        </form>
        <?php endif; ?>
    </div>
</div>
