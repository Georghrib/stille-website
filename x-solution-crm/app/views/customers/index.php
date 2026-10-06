<div class="page-head">
    <div>
        <div class="crumbs">CRM</div>
        <h1>Kunden</h1>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('/kunden/neu')) ?>"><?= icon('plus') ?> Neuer Kunde</a>
    </div>
</div>

<div class="tabs">
    <?php foreach (['' => 'Alle', 'kunde' => 'Kunden', 'interessent' => 'Interessenten', 'inaktiv' => 'Inaktiv'] as $key => $label): ?>
        <a href="<?= e(url('/kunden', array_filter(['status' => $key, 'q' => $q]))) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?><span class="count"><?= (int) $counts[$key] ?></span></a>
    <?php endforeach; ?>
</div>

<section class="card">
    <form method="get" class="toolbar">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, Ansprechpartner, E-Mail oder Ort suchen …">
        <button class="btn" type="submit"><?= icon('search') ?> Suchen</button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= e(url('/kunden', array_filter(['status' => $status]))) ?>">Zurücksetzen</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty-state"><?= icon('users') ?><p>Keine Kunden gefunden.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Kunde</th>
                <th>Status</th>
                <th class="table-hide-sm">Ort</th>
                <th class="right">Aktive Verträge</th>
                <th class="right">Umsatz p.&nbsp;a.</th>
                <th class="right table-hide-sm">Umsatz gesamt</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <a class="row-link" href="<?= e(url('/kunden/' . $r['id'])) ?>"><?= e($r['name']) ?></a>
                        <span class="cell-sub"><?= e($r['contact_person'] ?: ($r['email'] ?: '–')) ?></span>
                    </td>
                    <td><?= badge($r['status'], customer_status_label($r['status'])) ?></td>
                    <td class="table-hide-sm"><?= e(trim(($r['zip'] ?? '') . ' ' . ($r['city'] ?? '')) ?: '–') ?></td>
                    <td class="right"><?= (int) $r['active_contracts'] ?></td>
                    <td class="right nowrap"><?= money($r['annual_cents']) ?></td>
                    <td class="right nowrap table-hide-sm"><?= money($r['revenue_cents']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= App\Core\View::partial('partials/pagination', compact('total', 'page', 'per')) ?>
    <?php endif; ?>
</section>
