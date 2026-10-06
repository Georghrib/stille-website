<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    /** Bei Validierungsfehlern: Eingaben + Fehler merken und zurück zum Formular. */
    protected function failValidation(array $errors, string $target): never
    {
        $_SESSION['_old'] = array_map(fn ($v) => is_string($v) ? $v : '', $_POST);
        unset($_SESSION['_old']['_csrf']);
        $_SESSION['_errors'] = $errors;
        flash('error', implode(' ', array_values($errors)));
        redirect($target);
    }

    protected function notFound(string $what = 'Eintrag'): never
    {
        View::error(404, $what . ' nicht gefunden', 'Der angeforderte ' . $what . ' existiert nicht (mehr).');
    }

    /** Seitenweise Abfrage: liefert [offset, page, perPage]. */
    protected function paging(int $perPage = 25): array
    {
        $page = max(1, (int) ($_GET['seite'] ?? 1));
        return [($page - 1) * $perPage, $page, $perPage];
    }
}
