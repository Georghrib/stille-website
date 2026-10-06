<?php
$pages = (int) ceil($total / max(1, $per));
if ($pages > 1):
    $q = $_GET;
?>
<nav class="pagination" aria-label="Seiten">
    <?php for ($i = 1; $i <= $pages; $i++): $q['seite'] = $i; ?>
        <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?><a href="?<?= e(http_build_query($q)) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?><span>…</span><?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>
