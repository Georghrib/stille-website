<?php
use App\Core\View;
$k = $contract;
$isEdit = !empty($k['id']);
$selCustomer = (int) old('customer_id', (string) ($k['customer_id'] ?? ''));
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('/vertraege')) ?>">Verträge</a><?= $isEdit ? ' / <a href="' . e(url('/vertraege/' . $k['id'])) . '">' . e($k['title']) . '</a>' : '' ?></div>
        <h1><?= $isEdit ? 'Vertrag bearbeiten' : 'Neuer Vertrag' ?></h1>
    </div>
</div>

<section class="card">
    <?php if (!$customers): ?>
        <div class="alert alert-info">Lege zuerst einen <a href="<?= e(url('/kunden/neu')) ?>">Kunden</a> an.</div>
    <?php endif; ?>
    <form method="post" action="<?= e(url($isEdit ? '/vertraege/' . $k['id'] : '/vertraege')) ?>" class="form-grid" data-contract-form>
        <?= csrf_field() ?>
        <label class="span-2">Kunde *
            <select name="customer_id" required class="<?= isset($_SESSION['_errors']['customer_id']) ? 'field-error' : '' ?>">
                <option value="">Bitte wählen …</option>
                <?php foreach ($customers as $cu): ?>
                    <option value="<?= (int) $cu['id'] ?>" <?= $selCustomer === (int) $cu['id'] ? 'selected' : '' ?>><?= e($cu['name']) ?><?= $cu['status'] !== 'kunde' ? ' (' . e(customer_status_label($cu['status'])) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?= View::partial('contracts/_fields', ['contract' => $k]) ?>
        <div class="span-2 form-actions">
            <a class="btn btn-ghost" href="<?= e(url($isEdit ? '/vertraege/' . $k['id'] : '/vertraege')) ?>">Abbrechen</a>
            <button class="btn btn-primary" type="submit">Speichern</button>
        </div>
    </form>
</section>
