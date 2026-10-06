<?php
use App\Core\View;
use App\Services\ContractService;
$k = $contract;
$state = ContractService::classify($k);
$dur = ContractService::durationMonths($k);
$deadline = ContractService::noticeDeadline($k);
$daysDeadline = days_until($deadline);
$daysEnd = days_until($k['end_date']);
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('/vertraege')) ?>">Verträge</a> / <a href="<?= e(url('/kunden/' . $k['customer_id'])) ?>"><?= e($k['customer_name']) ?></a></div>
        <h1><?= e($k['title']) ?> <?= ContractService::stateBadge($k) ?></h1>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('/vertraege/' . $k['id'] . '/bearbeiten')) ?>"><?= icon('edit') ?> Bearbeiten</a>
    </div>
</div>

<?php if (in_array($state, ['verlaengerung', 'auslaufend'], true)): ?>
    <div class="alert alert-<?= $state === 'auslaufend' ? 'danger' : 'warn' ?>">
        <?php if ($state === 'auslaufend'): ?>
            Dieser Vertrag endet in <?= (int) $daysEnd ?> Tagen (<?= date_de($k['end_date']) ?>). Jetzt Verlängerung oder Nachfolgevertrag klären.
        <?php else: ?>
            Die Kündigungsfrist endet <?= $daysDeadline >= 0 ? 'in ' . (int) $daysDeadline . ' Tagen' : 'bereits' ?> (<?= date_de($deadline) ?>). Verlängerung mit dem Kunden besprechen.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-label">Monatswert</div><div class="kpi-value"><?= money($k['monthly_value_cents']) ?></div><span class="kpi-icon"><?= icon('euro') ?></span></div>
    <div class="card kpi"><div class="kpi-label"><?= $k['billing_interval'] === 'einmalig' ? 'Einmaliger Wert' : 'Umsatz p. a.' ?></div><div class="kpi-value"><?= money($k['billing_interval'] === 'einmalig' ? $k['total_value_cents'] : ContractService::annualValue($k)) ?></div><span class="kpi-icon"><?= icon('trend-up') ?></span></div>
    <div class="card kpi"><div class="kpi-label">Gesamtwert</div><div class="kpi-value"><?= money($k['total_value_cents']) ?></div><span class="kpi-icon"><?= icon('target') ?></span></div>
    <div class="card kpi"><div class="kpi-label">Bisher verrechnet</div><div class="kpi-value"><?= money($invoiced) ?></div><span class="kpi-icon"><?= icon('check') ?></span>
        <?php if ($k['total_value_cents'] > 0): ?><div class="progress"><span style="width: <?= min(100, round($invoiced / max(1, $k['total_value_cents']) * 100)) ?>%"></span></div><?php endif; ?>
    </div>
</div>

<div class="grid grid-2-1">
    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Vertragsdaten</h2></div>
            <dl class="details">
                <dt>Kunde</dt><dd><a href="<?= e(url('/kunden/' . $k['customer_id'])) ?>"><?= e($k['customer_name']) ?></a></dd>
                <dt>Status</dt><dd><?= e(contract_status_label($k['status'])) ?><?= $k['cancelled_at'] ? ' (gekündigt am ' . date_de($k['cancelled_at']) . ')' : '' ?></dd>
                <dt>Laufzeit</dt><dd><?= date_de($k['start_date']) ?> – <?= $k['end_date'] ? date_de($k['end_date']) : 'unbefristet' ?><?= $dur !== null ? ' (' . number_de($dur, $dur == floor($dur) ? 0 : 1) . ' Monate)' : '' ?></dd>
                <dt>Abrechnungsintervall</dt><dd><?= e(interval_label($k['billing_interval'])) ?></dd>
                <dt>Kündigungsfrist</dt><dd><?= (int) $k['notice_days'] ?> Tage<?= $deadline ? ' · spätestens kündigen bis ' . date_de($deadline) : '' ?></dd>
                <dt>Ursprungsdokument</dt><dd><?= $source ? '<a href="' . e(url('/easybill/' . $source['id'])) . '">' . e(doc_type_label($source['type']) . ' ' . ($source['number'] ?: '#' . $source['easybill_id'])) . '</a>' : '–' ?></dd>
                <dt>Angelegt</dt><dd><?= datetime_de($k['created_at']) ?><?= $k['creator'] ? ' von ' . e($k['creator']) : '' ?></dd>
            </dl>
            <?php if ($k['description']): ?><p class="muted prewrap mt-14"><?= e($k['description']) ?></p><?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Zugehörige easybill-Belege</h2></div>
            <?= View::partial('partials/documents', ['documents' => $documents, 'showCustomer' => false]) ?>
        </section>
    </div>

    <div class="stack">
        <?php if (in_array($k['status'], ['aktiv', 'entwurf'], true)): ?>
        <section class="card">
            <div class="card-head"><h2>Kündigung erfassen</h2></div>
            <form method="post" action="<?= e(url('/vertraege/' . $k['id'] . '/kuendigen')) ?>" class="stack-sm" data-confirm="Vertrag wirklich als gekündigt markieren?">
                <?= csrf_field() ?>
                <label>Vertragsende
                    <input name="end_date" data-date required value="<?= e($k['end_date'] ? date('d.m.Y', strtotime($k['end_date'])) : date('d.m.Y', strtotime('+' . (int) $k['notice_days'] . ' days'))) ?>">
                </label>
                <label>Grund (optional)<input name="reason" maxlength="300"></label>
                <div class="form-actions"><button class="btn btn-danger btn-sm" type="submit">Als gekündigt markieren</button></div>
            </form>
        </section>
        <?php endif; ?>

        <section class="card">
            <div class="card-head"><h2>Aufgaben &amp; Termine</h2></div>
            <?= View::partial('partials/task_form', ['customerId' => $k['customer_id'], 'contractId' => $k['id'], 'users' => $users, 'compact' => true]) ?>
            <?= View::partial('partials/tasks', ['tasks' => $tasks, 'showCustomer' => false]) ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Notizen zum Vertrag</h2></div>
            <form method="post" action="<?= e(url('/kunden/' . $k['customer_id'] . '/notizen')) ?>" class="stack-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="contract_id" value="<?= (int) $k['id'] ?>">
                <textarea name="body" required maxlength="5000" placeholder="Notiz zu diesem Vertrag …"></textarea>
                <div class="form-actions"><button class="btn btn-primary btn-sm" type="submit"><?= icon('note') ?> Speichern</button></div>
            </form>
            <?= View::partial('partials/notes', ['notes' => $notes]) ?>
        </section>

        <form method="post" action="<?= e(url('/vertraege/' . $k['id'] . '/loeschen')) ?>" data-confirm="Vertrag „<?= e($k['title']) ?>“ endgültig löschen?">
            <?= csrf_field() ?>
            <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Vertrag löschen</button>
        </form>
    </div>
</div>
