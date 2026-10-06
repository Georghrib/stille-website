<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

final class NoteController extends Controller
{
    public function store(int $id): void
    {
        $customer = Database::one('SELECT id FROM customers WHERE id = ?', [$id]) ?? $this->notFound('Kunde');
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 5000) {
            flash('error', 'Bitte einen Notiztext (max. 5000 Zeichen) eingeben.');
            back('/kunden/' . $id);
        }
        $contractId = (int) ($_POST['contract_id'] ?? 0) ?: null;
        if ($contractId && !Database::value('SELECT id FROM contracts WHERE id = ? AND customer_id = ?', [$contractId, $id])) {
            $contractId = null;
        }
        Database::insert('notes', ['customer_id' => $customer['id'], 'contract_id' => $contractId, 'user_id' => Auth::id(), 'body' => $body]);
        flash('success', 'Notiz gespeichert.');
        back('/kunden/' . $id);
    }

    public function destroy(int $id): void
    {
        $note = Database::one('SELECT * FROM notes WHERE id = ?', [$id]) ?? $this->notFound('Notiz');
        if ((int) $note['user_id'] !== Auth::id() && !Auth::isAdmin()) {
            flash('error', 'Nur eigene Notizen können gelöscht werden.');
            back('/kunden/' . $note['customer_id']);
        }
        Database::run('DELETE FROM notes WHERE id = ?', [$id]);
        flash('success', 'Notiz gelöscht.');
        back('/kunden/' . $note['customer_id']);
    }
}
