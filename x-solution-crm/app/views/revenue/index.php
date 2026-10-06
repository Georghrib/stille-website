<?php
$json = fn ($d) => json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$delta = pct_change($sumNowCut, $sumPrev);
?>
<div class="page-head">
    <div>
        <div class="crumbs">Auswertung</div>
        <h1>Umsatz <?= (int) $year ?></h1>
    </div>
    <div class="actions">
        <form method="get" class="inline-form">
            <select name="jahr" data-autosubmit aria-label="Jahr">
                <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
            </select>
        </form>
        <a class="btn" href="<?= e(url('/umsatz/export', ['jahr' => $year, 'art' => 'monate'])) ?>"><?= icon('download') ?> CSV Monate</a>
        <a class="btn btn-primary" href="<?= e(url('/umsatz/export', ['jahr' => $year])) ?>"><?= icon('download') ?> CSV Belege</a>
    </div>
</div>

<div class="grid grid-4">
    <section class="card kpi">
        <div class="kpi-label">Umsatz <?= (int) $year ?> (netto)</div>
        <div class="kpi-value"><?= money($sumNow) ?></div>
        <span class="kpi-icon"><?= icon('euro') ?></span>
        <div><?php if ($delta !== null): ?><span class="delta <?= $delta >= 0 ? 'up' : 'down' ?>"><?= icon($delta >= 0 ? 'arrow-up' : 'arrow-down') ?><?= ($delta >= 0 ? '+' : '−') . number_de(abs($delta), 1) ?> %</span><?php endif; ?><span class="muted">ggü. Vorjahr<?= $cut < 12 ? ' (Jän–' . MONTHS_DE_SHORT[$cut] . ')' : '' ?></span></div>
    </section>
    <section class="card kpi">
        <div class="kpi-label">Ø pro Monat</div>
        <div class="kpi-value"><?= money((int) round($sumNow / max(1, $cut))) ?></div>
        <span class="kpi-icon"><?= icon('chart') ?></span>
        <div class="muted small">über <?= (int) $cut ?> Monate</div>
    </section>
    <section class="card kpi">
        <div class="kpi-label">Vertragsbasis (MRR)</div>
        <div class="kpi-value"><?= money($mrr) ?></div>
        <span class="kpi-icon"><?= icon('trend-up') ?></span>
        <div class="muted small">monatlich wiederkehrend · <?= money($mrr * 12) ?> p.&nbsp;a.</div>
    </section>
    <section class="card kpi">
        <div class="kpi-label">Offene Forderungen</div>
        <div class="kpi-value"><?= money($openSum) ?></div>
        <span class="kpi-icon"><?= icon('clock') ?></span>
        <div class="muted small">unbezahlte Rechnungen (brutto)</div>
    </section>
</div>

<section class="card mb-20">
    <div class="card-head"><div><h2>Monatsverlauf <?= (int) $year ?></h2><span class="sub">Rechnungen netto abzgl. Gutschriften im Vergleich zur Vertragsbasis</span></div></div>
    <div class="chart-box"><canvas id="chart-revenue-year" role="img" aria-label="Umsatz pro Monat"></canvas></div>
    <script type="application/json" id="chart-revenue-year-data"><?= $json($chart) ?></script>
</section>

<div class="grid grid-2-1">
    <section class="card">
        <div class="card-head"><h2>Monatsübersicht</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Monat</th><th class="right">Rechnungen</th><th class="right">Gutschriften</th><th class="right">Umsatz netto</th><th class="right table-hide-sm">Vertragsbasis</th><th class="right table-hide-sm">offen (brutto)</th></tr></thead>
            <tbody>
            <?php foreach ($invoiced as $ym => $cents): $r = $detail[$ym] ?? null; ?>
                <tr>
                    <td><?= e(month_label($ym)) ?><span class="cell-sub"><?= (int) ($r['n'] ?? 0) ?> Rechnungen</span></td>
                    <td class="right nowrap"><?= money($r['inv'] ?? 0) ?></td>
                    <td class="right nowrap"><?= ($r['cred'] ?? 0) ? '−' . money($r['cred']) : money(0) ?></td>
                    <td class="right nowrap"><strong><?= money($cents) ?></strong></td>
                    <td class="right nowrap table-hide-sm"><?= money($basis[$ym] ?? 0) ?></td>
                    <td class="right nowrap table-hide-sm"><?= money($r['open_gross'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td><strong>Summe</strong></td><td></td><td></td><td class="right nowrap"><strong><?= money($sumNow) ?></strong></td><td class="table-hide-sm"></td><td class="table-hide-sm"></td></tr></tfoot>
        </table></div>
    </section>

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Top-Kunden <?= (int) $year ?></h2></div>
            <?php if (!$top): ?><p class="muted small">Noch keine Umsätze.</p><?php endif; ?>
            <?php $max = max(1, (int) ($top[0]['cents'] ?? 1)); foreach ($top as $t): ?>
                <div class="stat-row"><a class="trunc" href="<?= e(url('/kunden/' . $t['id'])) ?>"><?= e($t['name']) ?></a><span class="num"><?= money($t['cents']) ?></span></div>
                <div class="progress"><span style="width: <?= max(2, round($t['cents'] / $max * 100)) ?>%"></span></div>
            <?php endforeach; ?>
        </section>
        <section class="card">
            <div class="card-head"><h2>Offene Rechnungen</h2></div>
            <?php if (!$open): ?><p class="muted small">Alles bezahlt.</p><?php endif; ?>
            <ul class="list">
                <?php foreach ($open as $d): $late = $d['due_date'] && $d['due_date'] < date('Y-m-d'); ?>
                    <li class="list-item">
                        <span class="urgency <?= $late ? 'danger' : 'info' ?>"></span>
                        <div class="grow"><a class="title" href="<?= e(url('/easybill/' . $d['id'])) ?>"><?= e($d['number']) ?> · <?= e($d['display_name']) ?></a><span class="meta">fällig <?= date_de($d['due_date']) ?></span></div>
                        <span class="amount"><?= money($d['amount_gross_cents']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
