<ul class="checklist">
<?php foreach ($requirements as $r): ?>
    <li class="<?= $r['ok'] ? 'ok' : 'fail' ?>">
        <span class="dot"></span><?= e($r['label']) ?>
        <?php if (!$r['ok']): ?><small class="muted"> – <?= e($r['hint']) ?></small><?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
