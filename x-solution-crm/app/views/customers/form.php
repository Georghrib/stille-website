<?php
$c = $customer ?? [];
$isEdit = !empty($c['id']);
$v = fn (string $k, $d = '') => old($k, $c[$k] ?? $d);
$err = $_SESSION['_errors'] ?? [];
$cls = fn (string $k) => isset($err[$k]) ? 'field-error' : '';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('/kunden')) ?>">Kunden</a><?= $isEdit ? ' / <a href="' . e(url('/kunden/' . $c['id'])) . '">' . e($c['name']) . '</a>' : '' ?></div>
        <h1><?= $isEdit ? 'Kunde bearbeiten' : 'Neuer Kunde' ?></h1>
    </div>
</div>

<section class="card">
    <form method="post" action="<?= e(url($isEdit ? '/kunden/' . $c['id'] : '/kunden')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label class="span-2">Firmen- bzw. Kundenname *
            <input name="name" value="<?= e($v('name')) ?>" required maxlength="190" class="<?= $cls('name') ?>">
        </label>
        <label>Ansprechpartner
            <input name="contact_person" value="<?= e($v('contact_person')) ?>" maxlength="190">
        </label>
        <label>Status
            <select name="status">
                <?php foreach (['interessent' => 'Interessent', 'kunde' => 'Kunde', 'inaktiv' => 'Inaktiv'] as $k => $l): ?>
                    <option value="<?= $k ?>" <?= $v('status', 'interessent') === $k ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>E-Mail
            <input type="email" name="email" value="<?= e($v('email')) ?>" class="<?= $cls('email') ?>">
        </label>
        <label>Telefon
            <input name="phone" value="<?= e($v('phone')) ?>">
        </label>
        <label class="span-2">Straße
            <input name="street" value="<?= e($v('street')) ?>">
        </label>
        <label>PLZ
            <input name="zip" value="<?= e($v('zip')) ?>" maxlength="20">
        </label>
        <label>Ort
            <input name="city" value="<?= e($v('city')) ?>">
        </label>
        <label>Land (ISO-Code)
            <input name="country" value="<?= e($v('country', 'AT')) ?>" maxlength="2" class="<?= $cls('country') ?>">
        </label>
        <label>UID-Nummer
            <input name="vat_id" value="<?= e($v('vat_id')) ?>" placeholder="ATU12345678">
        </label>
        <label>easybill-Kunden-ID
            <input name="easybill_customer_id" value="<?= e($v('easybill_customer_id')) ?>" inputmode="numeric" class="<?= $cls('easybill_customer_id') ?>">
            <span class="field-hint">Wird beim Import automatisch gesetzt. Dient der Zuordnung neuer Belege.</span>
        </label>
        <div class="span-2 form-actions">
            <a class="btn btn-ghost" href="<?= e(url($isEdit ? '/kunden/' . $c['id'] : '/kunden')) ?>">Abbrechen</a>
            <button class="btn btn-primary" type="submit">Speichern</button>
        </div>
    </form>
</section>
