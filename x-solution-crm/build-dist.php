<?php
/**
 * Baut den Ordner dist/ neu auf – exakt die Struktur des Domain-Ordners beim Hoster:
 *
 *   dist/public_html/   <- Inhalt von public/
 *   dist/app/           <- PHP-Klassen, Templates
 *   dist/config/
 *   dist/storage/       <- leere Ordner pdfs/, logs/, cache/ (+ .htaccess)
 *   dist/.env.example
 *
 * Aufruf:  php build-dist.php
 * Eine evtl. vorhandene dist/.env bleibt erhalten, wird aber nie aus dem Projekt kopiert.
 */
declare(strict_types=1);

$root = __DIR__;
$dist = $root . '/dist';

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function rcopy(string $src, string $dst, array $skip = []): int
{
    $count = 0;
    @mkdir($dst, 0755, true);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($src) + 1);
        foreach ($skip as $pattern) {
            if (fnmatch($pattern, $rel) || fnmatch($pattern, basename($rel))) {
                continue 2;
            }
        }
        $target = $dst . '/' . $rel;
        if ($f->isDir()) {
            @mkdir($target, 0755, true);
        } else {
            @mkdir(dirname($target), 0755, true);
            copy($f->getPathname(), $target);
            $count++;
        }
    }
    return $count;
}

$keepEnv = is_file($dist . '/.env') ? file_get_contents($dist . '/.env') : null;
foreach (['public_html', 'app', 'config', 'storage'] as $d) {
    rrmdir($dist . '/' . $d);
}
@mkdir($dist, 0755, true);

$n = 0;
$n += rcopy($root . '/public', $dist . '/public_html');
$n += rcopy($root . '/app', $dist . '/app');
$n += rcopy($root . '/config', $dist . '/config');

// storage: nur Struktur + Schutzdateien, keine PDFs/Logs/Lock-Datei
foreach (['pdfs', 'logs', 'cache'] as $sub) {
    @mkdir($dist . '/storage/' . $sub, 0755, true);
    touch($dist . '/storage/' . $sub . '/.gitkeep');
}
copy($root . '/storage/.htaccess', $dist . '/storage/.htaccess');
copy($root . '/.env.example', $dist . '/.env.example');
copy($root . '/README.md', $dist . '/README.md');
$n += 4;

if ($keepEnv !== null) {
    file_put_contents($dist . '/.env', $keepEnv);
}

// Plausibilitätsprüfung: keine Zugangsdaten im Paket
$leaks = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getFilename() === '.env' || str_ends_with($f->getFilename(), '.lock')) {
        $leaks[] = substr($f->getPathname(), strlen($dist) + 1);
    }
}

echo "dist/ neu gebaut: {$n} Dateien.\n";
echo "  dist/public_html  -> in den Ordner public_html hochladen\n";
echo "  dist/app, dist/config, dist/storage, dist/.env.example -> in den Domain-Ordner (neben public_html)\n";
if ($leaks) {
    echo "Hinweis: Folgende lokale Dateien liegen in dist/ und dürfen NICHT ins Repository: " . implode(', ', $leaks) . "\n";
}
