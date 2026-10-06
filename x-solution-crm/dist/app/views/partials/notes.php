<?php if (!$notes): ?>
    <p class="muted small">Noch keine Notizen.</p>
<?php else: ?>
<ul class="timeline">
    <?php foreach ($notes as $n): ?>
        <li>
            <div class="meta">
                <span><?= datetime_de($n['created_at']) ?> · <?= e($n['user_name'] ?? 'System') ?><?= !empty($n['contract_title']) ? ' · ' . e($n['contract_title']) : '' ?></span>
                <form method="post" action="<?= e(url('/notizen/' . $n['id'] . '/loeschen')) ?>" class="inline-form" data-confirm="Notiz löschen?">
                    <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit" title="Löschen" aria-label="Notiz löschen"><?= icon('trash') ?></button>
                </form>
            </div>
            <div class="body"><?= e($n['body']) ?></div>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
