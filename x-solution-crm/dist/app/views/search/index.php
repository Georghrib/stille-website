<?php use App\Services\ContractService; ?>
<div class="page-head">
    <div>
        <div class="crumbs">Suche</div>
        <h1>Ergebnisse für „<?= e($q) ?>“</h1>
    </div>
</div>

<?php if (mb_strlen($q) < 2): ?>
    <section class="card"><p class="muted">Bitte mindestens zwei Zeichen eingeben.</p></section>
<?php elseif (!$customers && !$contracts && !$documents): ?>
    <section class="card"><div class="empty-state"><?= icon('search') ?><p>Keine Treffer.</p></div></section>
<?php else: ?>
<div class="stack">
    <?php if ($customers): ?>
    <section class="card">
        <div class="card-head"><h2>Kunden</h2><span class="sub"><?= count($customers) ?> Treffer</span></div>
        <ul class="list">
            <?php foreach ($customers as $c): ?>
                <li class="list-item">
                    <span class="avatar"><?= e(initials($c['name'])) ?></span>
                    <div class="grow"><a class="title" href="<?= e(url('/kunden/' . $c['id'])) ?>"><?= e($c['name']) ?></a><span class="meta"><?= e(implode(' · ', array_filter([$c['contact_person'], $c['email'], $c['city']]))) ?></span></div>
                    <?= badge($c['status'], customer_status_label($c['status'])) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <?php if ($contracts): ?>
    <section class="card">
        <div class="card-head"><h2>Verträge</h2><span class="sub"><?= count($contracts) ?> Treffer</span></div>
        <ul class="list">
            <?php foreach ($contracts as $k): ?>
                <li class="list-item">
                    <span class="doc-icon"><?= icon('file') ?></span>
                    <div class="grow"><a class="title" href="<?= e(url('/vertraege/' . $k['id'])) ?>"><?= e($k['title']) ?></a><span class="meta"><?= e($k['customer_name']) ?> · <?= date_de($k['start_date']) ?> – <?= $k['end_date'] ? date_de($k['end_date']) : 'unbefristet' ?></span></div>
                    <?= ContractService::stateBadge($k) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <?php if ($documents): ?>
    <section class="card">
        <div class="card-head"><h2>easybill-Belege</h2><span class="sub"><?= count($documents) ?> Treffer</span></div>
        <?= App\Core\View::partial('partials/documents', ['documents' => $documents, 'showCustomer' => true]) ?>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>
