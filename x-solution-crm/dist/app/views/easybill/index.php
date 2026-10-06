<div class="page-head">
    <div>
        <div class="crumbs">Integration</div>
        <h1>Aus easybill</h1>
    </div>
    <div class="actions">
        <form method="post" action="<?= e(url('/easybill/abgleich')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button class="btn" type="submit" <?= $apiConfigured ? '' : 'disabled title="Kein API-Key hinterlegt"' ?>><?= icon('refresh') ?> Jetzt abgleichen</button>
        </form>
    </div>
</div>

<?php if (!$apiConfigured): ?>
    <div class="alert alert-info">Noch kein easybill-API-Key hinterlegt. Belege kommen erst nach Eintrag des Keys unter <a href="<?= e(url('/einstellungen')) ?>">Einstellungen</a> bzw. per Webhook herein.</div>
<?php endif; ?>
<p class="muted small">Letzter Abgleich: <?= $lastSync ? datetime_de($lastSync) : 'noch nie' ?> · Letzter Cron-Lauf: <?= $lastCron ? datetime_de($lastCron) : 'noch nie' ?></p>

<div class="tabs">
    <?php foreach (['posteingang' => 'Posteingang', 'zugeordnet' => 'Zugeordnet', 'abgelegt' => 'Abgelegt', 'alle' => 'Alle Belege'] as $key => $label): ?>
        <a href="<?= e(url('/easybill', array_filter(['tab' => $key, 'typ' => $type, 'q' => $q]))) ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($label) ?><span class="count"><?= (int) $counts[$key] ?></span></a>
    <?php endforeach; ?>
</div>

<section class="card">
    <form method="get" class="toolbar">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Nummer, Titel oder Kunde …">
        <select name="typ" data-autosubmit aria-label="Belegtyp">
            <option value="">Alle Typen</option>
            <?php foreach (['angebot' => 'Angebote', 'rechnung' => 'Rechnungen', 'gutschrift' => 'Gutschriften'] as $k => $l): ?>
                <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= icon('search') ?> Suchen</button>
    </form>

    <?php if (!$rows): ?>
        <div class="empty-state"><?= icon('inbox') ?><p><?= $tab === 'posteingang' ? 'Posteingang leer – alle Belege sind zugeordnet.' : 'Keine Belege gefunden.' ?></p></div>
    <?php else: ?>
    <ul class="list">
        <?php foreach ($rows as $d): ?>
            <li class="list-item">
                <span class="doc-icon <?= e($d['type']) ?>"><?= icon($d['suggestion'] ? 'swap' : 'file') ?></span>
                <div class="grow">
                    <a class="title" href="<?= e(url('/easybill/' . $d['id'])) ?>"><?= e(doc_type_label($d['type'])) ?> <?= e($d['number'] ?: '#' . $d['easybill_id']) ?> · <?= e($d['crm_customer'] ?: ($d['customer_name'] ?: 'Unbekannter Kunde')) ?></a>
                    <span class="meta">
                        <?= e($d['title'] ?: 'ohne Titel') ?> · <?= date_de($d['document_date']) ?> ·
                        <?= badge($d['status'], doc_status_label($d['status'])) ?>
                        <?php if ($d['suggestion']): ?><?= badge('verlaengerung', 'Umstellung vorgeschlagen') ?><?php endif; ?>
                        <?php if ($d['inbox_state'] !== 'neu' && !$d['suggestion']): ?><?= badge($d['inbox_state'] === 'abgelegt' ? 'abgelegt' : 'aktiv', ['zugeordnet' => 'Zugeordnet', 'interessent' => 'Als Interessent', 'vertrag' => 'Als Vertrag', 'abgelegt' => 'Abgelegt'][$d['inbox_state']] ?? $d['inbox_state']) ?><?php endif; ?>
                    </span>
                </div>
                <span class="amount"><?= money($d['amount_net_cents']) ?><span class="cell-sub right">netto</span></span>
                <a class="btn btn-sm <?= $d['inbox_state'] === 'neu' || $d['suggestion'] ? 'btn-primary' : '' ?>" href="<?= e(url('/easybill/' . $d['id'])) ?>"><?= $d['suggestion'] ? 'Prüfen' : ($d['inbox_state'] === 'neu' ? 'Übernehmen' : 'Öffnen') ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?= App\Core\View::partial('partials/pagination', compact('total', 'page', 'per')) ?>
    <?php endif; ?>
</section>
