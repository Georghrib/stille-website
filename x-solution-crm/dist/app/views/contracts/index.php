<?php use App\Services\ContractService; ?>
<div class="page-head">
    <div>
        <div class="crumbs">CRM</div>
        <h1>Verträge</h1>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('/vertraege/neu')) ?>"><?= icon('plus') ?> Neuer Vertrag</a>
    </div>
</div>

<div class="tabs">
    <?php foreach (['laufend' => 'Laufend', 'aktiv' => 'Aktiv', 'gekuendigt' => 'Gekündigt', 'entwurf' => 'Entwürfe', 'beendet' => 'Beendet', 'alle' => 'Alle'] as $key => $label): ?>
        <a href="<?= e(url('/vertraege', array_filter(['status' => $key, 'q' => $q, 'sort' => $sort !== 'ende' ? $sort : null]))) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?><span class="count"><?= (int) $counts[$key] ?></span></a>
    <?php endforeach; ?>
</div>

<section class="card">
    <form method="get" class="toolbar">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Vertrag oder Kunde suchen …">
        <select name="sort" data-autosubmit aria-label="Sortierung">
            <?php foreach (['ende' => 'Sortierung: Vertragsende', 'start' => 'Sortierung: Neueste zuerst', 'wert' => 'Sortierung: Monatswert', 'kunde' => 'Sortierung: Kunde'] as $s => $l): ?>
                <option value="<?= $s ?>" <?= $sort === $s ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= icon('search') ?> Suchen</button>
    </form>
    <p class="muted small"><?= (int) $sum['n'] ?> Verträge · Monatlich wiederkehrend: <strong class="num"><?= money($sum['mrr']) ?></strong> · p.&nbsp;a.: <strong class="num"><?= money((int) $sum['mrr'] * 12) ?></strong></p>

    <?php if (!$rows): ?>
        <div class="empty-state"><?= icon('file') ?><p>Keine Verträge gefunden.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kunde</th><th>Vertrag</th><th>Status</th><th>Start</th><th>Ende</th><th class="table-hide-sm">Laufzeit</th><th class="right">Umsatz p.&nbsp;a.</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $dur = ContractService::durationMonths($r); ?>
                <tr>
                    <td><a class="row-link" href="<?= e(url('/kunden/' . $r['customer_id'])) ?>"><?= e($r['customer_name']) ?></a></td>
                    <td><a class="row-link" href="<?= e(url('/vertraege/' . $r['id'])) ?>"><?= e($r['title']) ?></a><span class="cell-sub"><?= e(interval_label($r['billing_interval'])) ?> · <?= (int) $r['notice_days'] ?> Tage Kündigungsfrist</span></td>
                    <td><?= ContractService::stateBadge($r) ?></td>
                    <td class="nowrap"><?= date_de($r['start_date']) ?></td>
                    <td class="nowrap"><?= date_de($r['end_date']) ?></td>
                    <td class="nowrap table-hide-sm"><?= $dur !== null ? number_de($dur, $dur == floor($dur) ? 0 : 1) . ' Monate' : 'unbefristet' ?></td>
                    <td class="right nowrap"><?= $r['billing_interval'] === 'einmalig' ? money($r['total_value_cents']) . '<span class="cell-sub">einmalig</span>' : money(ContractService::annualValue($r)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= App\Core\View::partial('partials/pagination', compact('total', 'page', 'per')) ?>
    <?php endif; ?>
</section>
