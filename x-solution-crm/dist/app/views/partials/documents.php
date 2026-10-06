<?php $showCustomer ??= true; ?>
<?php if (!$documents): ?>
    <div class="empty-state"><?= icon('inbox') ?><p>Keine Belege vorhanden.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Beleg</th><?php if ($showCustomer): ?><th>Kunde</th><?php endif; ?><th>Datum</th><th>Status</th><th class="right">Netto</th><th class="right table-hide-sm">Brutto</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $d): ?>
        <tr>
            <td>
                <a class="row-link" href="<?= e(url('/easybill/' . $d['id'])) ?>"><?= e(doc_type_label($d['type'])) ?> <?= e($d['number'] ?: '#' . $d['easybill_id']) ?></a>
                <span class="cell-sub"><?= e($d['title'] ?: '–') ?></span>
            </td>
            <?php if ($showCustomer): ?><td><?= e($d['customer_name'] ?? '–') ?></td><?php endif; ?>
            <td class="nowrap"><?= date_de($d['document_date']) ?></td>
            <td><?= badge($d['status'], doc_status_label($d['status'])) ?></td>
            <td class="right nowrap"><?= $d['type'] === 'gutschrift' ? '−' : '' ?><?= money($d['amount_net_cents']) ?></td>
            <td class="right nowrap table-hide-sm"><?= money($d['amount_gross_cents']) ?></td>
            <td class="right"><?php if ($d['pdf_path']): ?><a class="icon-btn" href="<?= e(url('/easybill/' . $d['id'] . '/pdf')) ?>" title="PDF öffnen" target="_blank" rel="noopener"><?= icon('download') ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
