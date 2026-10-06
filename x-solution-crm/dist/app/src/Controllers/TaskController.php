<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

final class TaskController extends Controller
{
    public function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'offen');
        $mine = ($_GET['meine'] ?? '1') === '1';
        $filters = [
            'offen' => 'a.done_at IS NULL',
            'heute' => 'a.done_at IS NULL AND DATE(a.due_at) = CURDATE()',
            'ueberfaellig' => 'a.done_at IS NULL AND a.due_at < NOW()',
            'woche' => 'a.done_at IS NULL AND a.due_at < DATE_ADD(CURDATE(), INTERVAL 8 DAY)',
            'erledigt' => 'a.done_at IS NOT NULL',
        ];
        if (!isset($filters[$tab])) {
            $tab = 'offen';
        }
        $userCond = $mine ? ' AND (a.assigned_to = ' . (int) Auth::id() . ' OR a.assigned_to IS NULL)' : '';
        $order = $tab === 'erledigt' ? 'a.done_at DESC' : 'a.due_at IS NULL, a.due_at ASC';
        $tasks = Database::all(
            "SELECT a.*, c.name AS customer_name, u.name AS user_name FROM appointments a
             LEFT JOIN customers c ON c.id = a.customer_id LEFT JOIN users u ON u.id = a.assigned_to
             WHERE {$filters[$tab]}$userCond ORDER BY $order LIMIT 200"
        );
        $counts = [];
        foreach ($filters as $k => $f) {
            $counts[$k] = (int) Database::value("SELECT COUNT(*) FROM appointments a WHERE $f$userCond");
        }
        $customers = Database::all("SELECT id, name FROM customers WHERE status <> 'inaktiv' ORDER BY name");
        $users = Database::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name');
        $this->view('tasks/index', compact('tasks', 'tab', 'mine', 'counts', 'customers', 'users') + ['pageTitle' => 'Aufgaben & Termine']);
    }

    public function store(): void
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $kind = ($_POST['kind'] ?? 'aufgabe') === 'termin' ? 'termin' : 'aufgabe';
        $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
        $due = $dueRaw === '' ? null : parse_datetime_de($dueRaw, (string) ($_POST['due_time'] ?? ''));
        $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
        $contractId = (int) ($_POST['contract_id'] ?? 0) ?: null;
        $assigned = (int) ($_POST['assigned_to'] ?? 0) ?: Auth::id();

        $errors = [];
        if ($title === '' || mb_strlen($title) > 190) {
            $errors[] = 'Bitte einen Titel (max. 190 Zeichen) angeben.';
        }
        if ($dueRaw !== '' && $due === null) {
            $errors[] = 'Das Datum ist ungültig (TT.MM.JJJJ).';
        }
        if ($kind === 'termin' && $due === null) {
            $errors[] = 'Ein Termin braucht ein Datum.';
        }
        if ($customerId && !Database::value('SELECT id FROM customers WHERE id = ?', [$customerId])) {
            $customerId = null;
        }
        if ($contractId && !Database::value('SELECT id FROM contracts WHERE id = ?', [$contractId])) {
            $contractId = null;
        }
        if (!Database::value('SELECT id FROM users WHERE id = ? AND active = 1', [$assigned])) {
            $assigned = Auth::id();
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            back('/aufgaben');
        }
        Database::insert('appointments', [
            'kind' => $kind, 'title' => $title, 'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'due_at' => $due, 'customer_id' => $customerId, 'contract_id' => $contractId,
            'assigned_to' => $assigned, 'created_by' => Auth::id(),
        ]);
        flash('success', ($kind === 'termin' ? 'Termin' : 'Aufgabe') . ' „' . $title . '“ angelegt.');
        back('/aufgaben');
    }

    public function toggle(int $id): void
    {
        $t = Database::one('SELECT * FROM appointments WHERE id = ?', [$id]) ?? $this->notFound('Eintrag');
        Database::run('UPDATE appointments SET done_at = ? WHERE id = ?', [$t['done_at'] ? null : date('Y-m-d H:i:s'), $id]);
        back('/aufgaben');
    }

    public function destroy(int $id): void
    {
        Database::one('SELECT id FROM appointments WHERE id = ?', [$id]) ?? $this->notFound('Eintrag');
        Database::run('DELETE FROM appointments WHERE id = ?', [$id]);
        flash('success', 'Eintrag gelöscht.');
        back('/aufgaben');
    }
}
