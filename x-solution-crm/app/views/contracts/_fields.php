<?php
/**
 * Gemeinsame Vertragsfelder (Vertragsformular und easybill-Übernahmedialog).
 * Erwartet $contract (Werte in Cent / ISO-Datum). Optional: $withDescription, $withStatus.
 */
$k = $contract ?? [];
$withDescription ??= true;
$withStatus ??= true;
$err = $_SESSION['_errors'] ?? [];
$cls = fn (string $f) => isset($err[$f]) ? 'field-error' : '';
$o = fn (string $f, $d = '') => old($f, $d);
$dateVal = fn (string $f) => $o($f, isset($k[$f]) && $k[$f] ? date('d.m.Y', strtotime($k[$f])) : '');
?>
<label class="span-2">Vertragstitel *
    <input name="title" value="<?= e($o('title', $k['title'] ?? '')) ?>" required maxlength="190" class="<?= $cls('title') ?>" placeholder="z. B. Wartungsvertrag Website">
</label>
<?php if ($withStatus): ?>
<label>Status
    <select name="status">
        <?php foreach (['entwurf' => 'Entwurf', 'aktiv' => 'Aktiv', 'gekuendigt' => 'Gekündigt', 'beendet' => 'Beendet'] as $s => $l): ?>
            <option value="<?= $s ?>" <?= $o('status', $k['status'] ?? 'aktiv') === $s ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
</label>
<?php endif; ?>
<label>Abrechnungsintervall
    <select name="billing_interval">
        <?php foreach (['monatlich' => 'Monatlich', 'quartal' => 'Quartalsweise', 'jaehrlich' => 'Jährlich', 'einmalig' => 'Einmalig'] as $s => $l): ?>
            <option value="<?= $s ?>" <?= $o('billing_interval', $k['billing_interval'] ?? 'monatlich') === $s ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
</label>
<label>Laufzeit von *
    <input name="start_date" data-date value="<?= e($dateVal('start_date')) ?>" placeholder="TT.MM.JJJJ" required class="<?= $cls('start_date') ?>">
</label>
<label>Laufzeit bis
    <input name="end_date" data-date value="<?= e($dateVal('end_date')) ?>" placeholder="TT.MM.JJJJ (leer = unbefristet)" class="<?= $cls('end_date') ?>">
    <span class="field-hint">
        Schnellwahl:
        <?php foreach ([6, 12, 24, 36] as $m): ?><button type="button" class="btn btn-ghost btn-sm" data-term="<?= $m ?>"><?= $m ?> M.</button><?php endforeach; ?>
        · Laufzeit: <strong data-duration>–</strong>
    </span>
</label>
<label>Gesamtwert (netto, €)
    <input name="total_value" data-money inputmode="decimal" value="<?= e($o('total_value', money_input($k['total_value_cents'] ?? null))) ?>" placeholder="0,00" class="<?= $cls('total_value') ?>">
</label>
<label>Monatswert (netto, €)
    <input name="monthly_value" data-money inputmode="decimal" value="<?= e($o('monthly_value', money_input($k['monthly_value_cents'] ?? null))) ?>" placeholder="automatisch">
    <span class="field-hint" data-monthly-hint></span>
</label>
<label>Kündigungsfrist (Tage)
    <input name="notice_days" inputmode="numeric" value="<?= e($o('notice_days', (string) ($k['notice_days'] ?? 90))) ?>" class="<?= $cls('notice_days') ?>">
</label>
<?php if ($withDescription): ?>
<label class="span-2">Beschreibung / Leistungsumfang
    <textarea name="description" maxlength="5000"><?= e($o('description', $k['description'] ?? '')) ?></textarea>
</label>
<?php endif; ?>
