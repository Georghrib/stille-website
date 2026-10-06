<?php $showCustomer ??= true; ?>
<?php if (!$tasks): ?>
    <p class="muted small">Keine Aufgaben oder Termine.</p>
<?php else: ?>
<div>
<?php foreach ($tasks as $t):
    $overdue = !$t['done_at'] && $t['due_at'] && $t['due_at'] < date('Y-m-d H:i:s');
?>
    <div class="task <?= $t['done_at'] ? 'done' : '' ?>">
        <form method="post" action="<?= e(url('/aufgaben/' . $t['id'] . '/erledigt')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button class="check-btn" type="submit" title="<?= $t['done_at'] ? 'Wieder öffnen' : 'Als erledigt markieren' ?>" aria-label="Erledigt umschalten"><?= icon('check') ?></button>
        </form>
        <div class="grow">
            <span class="title"><?= e($t['title']) ?></span>
            <span class="cell-sub">
                <span class="kind-pill"><?= $t['kind'] === 'termin' ? 'Termin' : 'Aufgabe' ?></span>
                <?php if ($t['due_at']): ?> · <span class="<?= $overdue ? 'text-danger' : '' ?>"><?= datetime_de($t['due_at']) ?></span><?php endif; ?>
                <?php if ($showCustomer && !empty($t['customer_name'])): ?> · <a href="<?= e(url('/kunden/' . $t['customer_id'])) ?>"><?= e($t['customer_name']) ?></a><?php endif; ?>
                <?php if (!empty($t['user_name'])): ?> · <?= e($t['user_name']) ?><?php endif; ?>
            </span>
            <?php if (!empty($t['description'])): ?><span class="cell-sub"><?= e($t['description']) ?></span><?php endif; ?>
        </div>
        <?php if ($overdue): ?><span class="days-pill danger">überfällig</span><?php endif; ?>
        <form method="post" action="<?= e(url('/aufgaben/' . $t['id'] . '/loeschen')) ?>" class="inline-form" data-confirm="Eintrag löschen?">
            <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" aria-label="Löschen"><?= icon('trash') ?></button>
        </form>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
