<?php
use App\Core\View;
$d = $doc;
$label = doc_type_label($d['type']) . ' ' . ($d['number'] ?: '#' . $d['easybill_id']);
$isOpen = $d['inbox_state'] === 'neu';
$customerSelect = function (string $selected) use ($customers, $prefillCustomer): string {
    ob_start(); ?>
    <label class="span-2">Kunde
        <select name="customer_choice">
            <option value="neu" <?= $selected === 'neu' ? 'selected' : '' ?>>➕ Neuen Kunden aus easybill-Daten anlegen</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $selected === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e(customer_status_label($c['status'])) ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="span-2 form-grid" data-new-customer>
        <label>Name *<input name="customer_name" value="<?= e(old('customer_name', $prefillCustomer['name'])) ?>" maxlength="190"></label>
        <label>Ansprechpartner<input name="customer_contact" value="<?= e(old('customer_contact', $prefillCustomer['contact_person'])) ?>" maxlength="190"></label>
        <label class="span-2">E-Mail<input type="email" name="customer_email" value="<?= e(old('customer_email', $prefillCustomer['email'])) ?>"></label>
        <?php if ($prefillCustomer['city'] || $prefillCustomer['street']): ?>
            <p class="span-2 field-hint">Adresse aus easybill: <?= e(trim($prefillCustomer['street'] . ', ' . $prefillCustomer['zip'] . ' ' . $prefillCustomer['city'], ', ')) ?></p>
        <?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};
$selectedCustomer = $suggestedCustomer ? (string) $suggestedCustomer : 'neu';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('/easybill')) ?>">Aus easybill</a> / <?= e($label) ?></div>
        <h1><?= e($label) ?> <?= badge($d['type'], doc_type_label($d['type'])) ?> <?= badge($d['status'], doc_status_label($d['status'])) ?></h1>
    </div>
    <div class="actions">
        <?php if ($d['pdf_path'] || (env('EASYBILL_API_KEY') && empty($raw['demo']) && !$d['is_draft'])): ?>
            <a class="btn" href="<?= e(url('/easybill/' . $d['id'] . '/pdf')) ?>" target="_blank" rel="noopener"><?= icon('download') ?> PDF öffnen</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($d['suggestion'] === 'umstellung'): ?>
<section class="card mb-20">
    <div class="suggestion">
        <?= icon('swap') ?>
        <div class="grow">
            <strong>Angebot <?= e($offer['number'] ?? '?') ?> wurde in easybill zur Rechnung <?= e($d['number']) ?>.</strong>
            <span class="cell-sub">Vorschlag: <?= $offerContract ? 'Vertragsentwurf „' . e($offerContract['title']) . '“ aktivieren' : 'Vertrag anlegen' ?>, Kunde auf „Kunde“ umstellen und beide Belege verknüpfen. Es wird erst nach deiner Bestätigung etwas geändert.</span>
        </div>
        <form method="post" action="<?= e(url('/easybill/' . $d['id'] . '/vorschlag-verwerfen')) ?>" class="inline-form">
            <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Vorschlag verwerfen</button>
        </form>
    </div>
    <form method="post" action="<?= e(url('/easybill/' . $d['id'] . '/umstellen')) ?>" class="form-grid" data-contract-form data-accept-form-convert>
        <?= csrf_field() ?>
        <?= $customerSelect($selectedCustomer) ?>
        <?= View::partial('contracts/_fields', ['contract' => $prefillContract, 'withDescription' => false, 'withStatus' => false]) ?>
        <div class="span-2 form-actions">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Umstellung bestätigen</button>
        </div>
    </form>
</section>
<?php endif; ?>

<div class="grid grid-2-1">
    <div class="stack">
        <?php if ($isOpen || isset($_GET['uebernehmen'])): ?>
        <section class="card" id="uebernahme">
            <div class="card-head"><div><h2>Ins CRM übernehmen</h2><span class="sub">Wie soll dieser Beleg übernommen werden?</span></div></div>
            <form method="post" action="<?= e(url('/easybill/' . $d['id'] . '/uebernehmen')) ?>" data-accept-form data-contract-form>
                <?= csrf_field() ?>
                <?php $mode = old('mode', $d['type'] === 'rechnung' ? 'vertrag' : 'interessent'); ?>
                <div class="choice-grid" role="radiogroup">
                    <label class="choice"><input type="radio" name="mode" value="interessent" <?= $mode === 'interessent' ? 'checked' : '' ?>>
                        <span class="box"><strong><?= icon('user-plus') ?> Interessent</strong><span>Kunde mit Status „Interessent“ anlegen bzw. wählen und den Beleg verknüpfen.</span></span></label>
                    <label class="choice"><input type="radio" name="mode" value="vertrag" <?= $mode === 'vertrag' ? 'checked' : '' ?>>
                        <span class="box"><strong><?= icon('file') ?> Vertrag</strong><span>Kunde aktivieren und mit Laufzeit, Intervall und Kündigungsfrist einen Vertrag erzeugen.</span></span></label>
                    <label class="choice"><input type="radio" name="mode" value="ablegen" <?= $mode === 'ablegen' ? 'checked' : '' ?>>
                        <span class="box"><strong><?= icon('archive') ?> Nur ablegen</strong><span>Beleg ohne Zuordnung archivieren. Er verschwindet aus dem Posteingang.</span></span></label>
                </div>
                <div class="form-grid option-panel" data-panel="interessent vertrag">
                    <?= $customerSelect($selectedCustomer) ?>
                </div>
                <div class="form-grid option-panel" data-panel="vertrag">
                    <?= View::partial('contracts/_fields', ['contract' => $prefillContract, 'withDescription' => false]) ?>
                </div>
                <div class="form-actions mt-14">
                    <a class="btn btn-ghost" href="<?= e(url('/easybill')) ?>">Abbrechen</a>
                    <button class="btn btn-primary" type="submit" data-submit-label>Übernehmen</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section class="card">
            <div class="card-head"><h2>Belegdaten</h2></div>
            <dl class="details">
                <dt>Typ / Nummer</dt><dd><?= e($label) ?></dd>
                <dt>Titel</dt><dd><?= e($d['title'] ?: '–') ?></dd>
                <dt>Kunde laut easybill</dt><dd><?= e($d['customer_name'] ?: '–') ?><?= $d['customer_email'] ? ' · ' . e($d['customer_email']) : '' ?><?= $d['easybill_customer_id'] ? ' <span class="muted">(easybill-ID ' . e($d['easybill_customer_id']) . ')</span>' : '' ?></dd>
                <dt>Belegdatum</dt><dd><?= date_de($d['document_date']) ?></dd>
                <?php if ($d['type'] === 'rechnung'): ?>
                    <dt>Fällig am</dt><dd><?= date_de($d['due_date']) ?></dd>
                    <dt>Bezahlt am</dt><dd><?= date_de($d['paid_at']) ?></dd>
                <?php endif; ?>
                <dt>Betrag netto</dt><dd class="num"><?= money($d['amount_net_cents']) ?></dd>
                <dt>Betrag brutto</dt><dd class="num"><?= money($d['amount_gross_cents']) ?></dd>
                <dt>easybill-ID</dt><dd><?= e($d['easybill_id']) ?><?= $d['ref_easybill_id'] ? ' · Referenz: ' . e($d['ref_easybill_id']) : '' ?></dd>
                <dt>Importiert / aktualisiert</dt><dd><?= datetime_de($d['imported_at']) ?> / <?= datetime_de($d['updated_at']) ?></dd>
                <dt>PDF</dt><dd><?= $d['pdf_path'] ? 'gespeichert' : 'noch nicht geladen' ?></dd>
            </dl>
        </section>

        <?php if ($related): ?>
        <section class="card">
            <div class="card-head"><h2>Verknüpfte Belege</h2></div>
            <?= View::partial('partials/documents', ['documents' => $related, 'showCustomer' => true]) ?>
        </section>
        <?php endif; ?>

        <?php if (is_array($raw)): ?>
        <details class="card">
            <summary class="muted">Originaldaten von easybill (JSON)</summary>
            <pre class="code json mt-14"><?= e(json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Zuordnung im CRM</h2></div>
            <dl class="details">
                <dt>Status</dt><dd><?= e(['neu' => 'Im Posteingang', 'zugeordnet' => 'Automatisch zugeordnet', 'interessent' => 'Als Interessent übernommen', 'vertrag' => 'Als Vertrag übernommen', 'abgelegt' => 'Abgelegt'][$d['inbox_state']] ?? $d['inbox_state']) ?></dd>
                <dt>Kunde</dt><dd><?= $customer ? '<a href="' . e(url('/kunden/' . $customer['id'])) . '">' . e($customer['name']) . '</a> ' . badge($customer['status'], customer_status_label($customer['status'])) : '–' ?></dd>
                <dt>Vertrag</dt><dd><?= $contract ? '<a href="' . e(url('/vertraege/' . $contract['id'])) . '">' . e($contract['title']) . '</a>' : '–' ?></dd>
            </dl>
            <div class="actions mt-14">
                <?php if (!$isOpen && !$contract && !isset($_GET['uebernehmen'])): ?>
                    <a class="btn btn-sm" href="<?= e(url('/easybill/' . $d['id'], ['uebernehmen' => 1])) ?>#uebernahme"><?= icon('file') ?> Als Vertrag/Interessent übernehmen</a>
                <?php endif; ?>
                <?php if ($d['inbox_state'] === 'abgelegt'): ?>
                    <form method="post" action="<?= e(url('/easybill/' . $d['id'] . '/zuruecksetzen')) ?>" class="inline-form">
                        <?= csrf_field() ?><button class="btn btn-sm" type="submit"><?= icon('inbox') ?> Zurück in den Posteingang</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <?php if ($offer): ?>
        <section class="card">
            <div class="card-head"><h2>Ursprüngliches Angebot</h2></div>
            <p><a href="<?= e(url('/easybill/' . $offer['id'])) ?>">Angebot <?= e($offer['number']) ?></a> vom <?= date_de($offer['document_date']) ?> · <?= money($offer['amount_net_cents']) ?> netto</p>
        </section>
        <?php endif; ?>
    </div>
</div>
