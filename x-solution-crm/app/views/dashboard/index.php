<?php
use App\Services\ContractService;
$json = fn ($d) => json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$deltaHtml = function (?float $delta, string $unit): string {
    if ($delta === null) {
        return '<span class="delta flat">neu</span>';
    }
    $cls = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
    $ic = $delta > 0 ? icon('arrow-up') : ($delta < 0 ? icon('arrow-down') : '');
    $num = $unit === '' ? number_de(abs($delta)) : number_de(abs($delta), 1);
    return '<span class="delta ' . $cls . '">' . $ic . ($delta > 0 ? '+' : ($delta < 0 ? '−' : '±')) . $num . e($unit === '%' ? ' %' : $unit) . '</span>';
};
$statusColors = $statusChart['colors'];
$distTotal = array_sum($dist);
?>
<!-- 1. KPI-Kacheln -->
<div class="grid grid-4">
    <?php foreach ($kpis as $k): ?>
        <section class="card kpi">
            <div class="kpi-label"><?= e($k['label']) ?></div>
            <div class="kpi-value"><?= e($k['value']) ?></div>
            <span class="kpi-icon"><?= icon($k['icon']) ?></span>
            <div><?= $deltaHtml($k['delta'] === null ? null : (float) $k['delta'], $k['unit']) ?><span class="muted"><?= e($k['hint']) ?></span></div>
        </section>
    <?php endforeach; ?>
</div>

<!-- 2. Umsatzentwicklung + Vertragslaufzeiten -->
<div class="grid grid-2-1">
    <section class="card">
        <div class="card-head">
            <div><h2>Umsatzentwicklung</h2><span class="sub">Rechnungen netto abzgl. Gutschriften · <?= e(month_label(App\Services\Period::shift($p, -11), true)) ?> – <?= e(month_label($p, true)) ?></span></div>
            <a class="link-more" href="<?= e(url('/umsatz')) ?>">Auswertung <?= icon('chevron-right') ?></a>
        </div>
        <div class="chart-box"><canvas id="chart-revenue" aria-label="Liniendiagramm Umsatzentwicklung" role="img"></canvas></div>
        <script type="application/json" id="chart-revenue-data"><?= $json($revenueChart) ?></script>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Vertragslaufzeiten</h2><span class="sub">Laufende Verträge nach Laufzeit in Monaten</span></div></div>
        <div class="chart-box"><canvas id="chart-durations" aria-label="Balkendiagramm Vertragslaufzeiten" role="img"></canvas></div>
        <script type="application/json" id="chart-durations-data"><?= $json($durationChart) ?></script>
    </section>
</div>

<!-- 3. Aktive Verträge -->
<section class="card mb-20">
    <div class="card-head">
        <div><h2>Aktive Verträge</h2><span class="sub"><?= (int) $contractCount ?> laufende Verträge, sortiert nach Vertragsende</span></div>
        <a class="link-more" href="<?= e(url('/vertraege')) ?>">Alle Verträge anzeigen <?= icon('chevron-right') ?></a>
    </div>
    <?php if (!$contracts): ?>
        <div class="empty-state"><?= icon('file') ?><p>Noch keine aktiven Verträge. <a href="<?= e(url('/vertraege/neu')) ?>">Ersten Vertrag anlegen</a></p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kunde</th><th>Status</th><th>Start</th><th>Ende</th><th class="table-hide-sm">Laufzeit</th><th class="right">Umsatz p.&nbsp;a.</th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c): $dur = ContractService::durationMonths($c); ?>
                <tr>
                    <td><a class="row-link" href="<?= e(url('/vertraege/' . $c['id'])) ?>"><?= e($c['customer_name']) ?></a><span class="cell-sub"><?= e($c['title']) ?></span></td>
                    <td><?= ContractService::stateBadge($c) ?></td>
                    <td class="nowrap"><?= date_de($c['start_date']) ?></td>
                    <td class="nowrap"><?= $c['end_date'] ? date_de($c['end_date']) : 'unbefristet' ?></td>
                    <td class="nowrap table-hide-sm"><?= $dur !== null ? number_de($dur, $dur == floor($dur) ? 0 : 1) . ' Monate' : '–' ?></td>
                    <td class="right nowrap"><?= $c['billing_interval'] === 'einmalig' ? money($c['total_value_cents']) . '<span class="cell-sub">einmalig</span>' : money(ContractService::annualValue($c)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<!-- 4. Vertragsstatus + Prognose + Opportunities -->
<div class="grid grid-1-1-1">
    <section class="card">
        <div class="card-head"><div><h2>Vertragsstatus</h2><span class="sub">Stand heute</span></div></div>
        <div class="donut-row">
            <div class="chart-box sm"><canvas id="chart-status" aria-label="Donutdiagramm Vertragsstatus" role="img"></canvas></div>
            <ul class="legend">
                <?php $i = 0; foreach ($dist as $key => $n): ?>
                    <li><span class="sw" style="background:<?= e($statusColors[$i++]) ?>"></span><?= e(ContractService::STATES[$key]) ?><span class="val"><?= (int) $n ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <script type="application/json" id="chart-status-data"><?= $json($statusChart) ?></script>
    </section>

    <section class="card kpi">
        <div class="kpi-label">Umsatzprognose</div>
        <span class="kpi-icon"><?= icon('trend-up') ?></span>
        <div class="stat-big"><?= money($forecast['total']) ?></div>
        <p class="muted small">für <?= e(month_label($nextYm)) ?> aus <?= (int) $forecast['contracts'] ?> aktiven Verträgen</p>
        <div class="stat-row"><span class="muted">Wiederkehrend</span><span class="num"><?= money($forecast['recurring']) ?></span></div>
        <div class="stat-row"><span class="muted">Einmalig</span><span class="num"><?= money($forecast['one_off']) ?></span></div>
        <div class="stat-row"><span class="muted">Vergleich <?= e(month_label(date('Y-m'))) ?></span><span><?php $d = pct_change($forecast['total'], $forecastPrev['total']); ?><span class="delta <?= $d === null || $d == 0 ? 'flat' : ($d > 0 ? 'up' : 'down') ?>"><?= $d === null ? '–' : ($d > 0 ? '+' : ($d < 0 ? '−' : '±')) . number_de(abs($d), 1) . ' %' ?></span></span></div>
    </section>

    <section class="card kpi">
        <div class="kpi-label">Offene Opportunities</div>
        <span class="kpi-icon"><?= icon('target') ?></span>
        <div class="stat-big"><?= money($opps['total']) ?></div>
        <p class="muted small"><?= (int) $opps['count'] ?> offene<?= $opps['count'] === 1 ? 's Angebot' : ' Angebote' ?> aus easybill (netto)</p>
        <?php foreach ($opps['top'] as $o): ?>
            <div class="stat-row"><a href="<?= e(url('/easybill/' . $o['id'])) ?>" class="trunc"><?= e($o['display_name'] ?: $o['number']) ?></a><span class="num"><?= money($o['amount_net_cents']) ?></span></div>
        <?php endforeach; ?>
        <?php if ($opps['total'] > 0 && $forecast['total'] > 0): ?>
            <div class="progress" title="Verhältnis zur Monatsprognose"><span style="width: <?= min(100, round($forecast['total'] / ($forecast['total'] + $opps['total']) * 100)) ?>%"></span></div>
        <?php endif; ?>
    </section>
</div>

<!-- 5. Aus easybill + Erinnerungen -->
<div class="grid grid-2">
    <section class="card">
        <div class="card-head">
            <div><h2>Aus easybill</h2><span class="sub"><?= (int) $inboxTotal ?> nicht zugeordnete Belege &amp; Vorschläge</span></div>
            <a class="link-more" href="<?= e(url('/easybill')) ?>">Posteingang öffnen <?= icon('chevron-right') ?></a>
        </div>
        <?php if (!$inbox): ?>
            <div class="empty-state"><?= icon('inbox') ?><p>Posteingang leer – alle Belege sind zugeordnet.</p></div>
        <?php else: ?>
        <ul class="list">
            <?php foreach ($inbox as $d): ?>
                <li class="list-item">
                    <span class="doc-icon <?= e($d['type']) ?>"><?= icon($d['suggestion'] ? 'swap' : 'file') ?></span>
                    <div class="grow">
                        <a class="title" href="<?= e(url('/easybill/' . $d['id'])) ?>"><?= e(doc_type_label($d['type'])) ?> <?= e($d['number'] ?: '#' . $d['easybill_id']) ?> · <?= e($d['customer_name'] ?: 'Unbekannter Kunde') ?></a>
                        <span class="meta"><?= $d['suggestion'] ? 'Vorschlag: Angebot wurde zur Rechnung – Umstellung bestätigen' : e($d['title'] ?: 'ohne Titel') . ' · ' . date_de($d['document_date']) ?></span>
                    </div>
                    <span class="amount"><?= money($d['amount_net_cents']) ?></span>
                    <a class="btn btn-sm <?= $d['suggestion'] ? 'btn-primary' : '' ?>" href="<?= e(url('/easybill/' . $d['id'])) ?>"><?= $d['suggestion'] ? 'Prüfen' : 'Übernehmen' ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <div><h2>Erinnerungen &amp; Verlängerungen</h2><span class="sub">Vertragsenden und Kündigungsfristen der nächsten 120 Tage</span></div>
            <a class="link-more" href="<?= e(url('/vertraege', ['sort' => 'ende'])) ?>">Alle <?= icon('chevron-right') ?></a>
        </div>
        <?php if (!$reminders): ?>
            <div class="empty-state"><?= icon('calendar') ?><p>Keine anstehenden Vertragsenden.</p></div>
        <?php else: ?>
        <ul class="list">
            <?php foreach ($reminders as $r): ?>
                <li class="list-item">
                    <span class="urgency <?= e($r['tone']) ?>"></span>
                    <div class="grow">
                        <a class="title" href="<?= e(url('/vertraege/' . $r['id'])) ?>"><?= e($r['customer_name']) ?> · <?= e($r['title']) ?></a>
                        <span class="meta"><?= e($r['text']) ?></span>
                    </div>
                    <span class="days-pill <?= e($r['tone']) ?>"><?= (int) $r['days'] === 0 ? 'heute' : 'in ' . (int) $r['days'] . ' T.' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
