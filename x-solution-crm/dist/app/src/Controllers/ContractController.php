<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\ContractService;

final class ContractController extends Controller
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'laufend');
        $sort = (string) ($_GET['sort'] ?? 'ende');
        [$offset, $page, $per] = $this->paging(30);

        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(c.title LIKE :q OR cu.name LIKE :q2)';
            $params += ['q' => "%$q%", 'q2' => "%$q%"];
        }
        $filters = [
            'laufend' => "c.status IN ('aktiv','gekuendigt')",
            'aktiv' => "c.status = 'aktiv'",
            'gekuendigt' => "c.status = 'gekuendigt'",
            'entwurf' => "c.status = 'entwurf'",
            'beendet' => "c.status = 'beendet'",
            'alle' => '1=1',
        ];
        if (!isset($filters[$status])) {
            $status = 'laufend';
        }
        $where[] = $filters[$status];
        $orders = [
            'ende' => '(c.end_date IS NULL), c.end_date ASC',
            'start' => 'c.start_date DESC',
            'wert' => 'c.monthly_value_cents DESC',
            'kunde' => 'cu.name ASC, c.title ASC',
        ];
        $order = $orders[$sort] ?? $orders['ende'];
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM contracts c JOIN customers cu ON cu.id = c.customer_id WHERE $w", $params);
        $rows = Database::all("SELECT c.*, cu.name AS customer_name FROM contracts c JOIN customers cu ON cu.id = c.customer_id WHERE $w ORDER BY $order LIMIT $per OFFSET $offset", $params);
        $sum = Database::one(
            "SELECT COALESCE(SUM(CASE WHEN c.billing_interval <> 'einmalig' THEN c.monthly_value_cents ELSE 0 END),0) AS mrr, COUNT(*) AS n
             FROM contracts c JOIN customers cu ON cu.id = c.customer_id WHERE $w",
            $params
        );
        $counts = [];
        foreach ($filters as $k => $f) {
            $counts[$k] = (int) Database::value("SELECT COUNT(*) FROM contracts c WHERE $f");
        }
        $this->view('contracts/index', compact('rows', 'q', 'status', 'sort', 'counts', 'total', 'page', 'per', 'sum') + ['pageTitle' => 'Verträge']);
    }

    public function create(): void
    {
        $contract = [
            'customer_id' => (int) ($_GET['kunde'] ?? 0) ?: null,
            'status' => 'aktiv', 'billing_interval' => 'monatlich', 'notice_days' => 90,
            'start_date' => date('Y-m-01', strtotime('first day of next month')),
        ];
        $this->view('contracts/form', ['pageTitle' => 'Neuer Vertrag', 'contract' => $contract, 'customers' => $this->customers()]);
    }

    public function store(): void
    {
        [$data, $errors] = ContractService::fromInput($_POST);
        if ($errors) {
            $this->failValidation($errors, '/vertraege/neu' . (!empty($_POST['customer_id']) ? '?kunde=' . (int) $_POST['customer_id'] : ''));
        }
        $data['created_by'] = Auth::id();
        $data['cancelled_at'] = $data['status'] === 'gekuendigt' ? date('Y-m-d') : null;
        $id = Database::transaction(function () use ($data) {
            $id = Database::insert('contracts', $data);
            if (in_array($data['status'], ['aktiv', 'gekuendigt'], true)) {
                Database::run("UPDATE customers SET status = 'kunde' WHERE id = ? AND status <> 'kunde'", [$data['customer_id']]);
            }
            return $id;
        });
        flash('success', 'Vertrag „' . $data['title'] . '“ wurde angelegt.');
        redirect('/vertraege/' . $id);
    }

    public function show(int $id): void
    {
        $contract = Database::one('SELECT c.*, cu.name AS customer_name, cu.status AS customer_status, u.name AS creator FROM contracts c JOIN customers cu ON cu.id = c.customer_id LEFT JOIN users u ON u.id = c.created_by WHERE c.id = ?', [$id])
            ?? $this->notFound('Vertrag');
        $documents = Database::all('SELECT * FROM easybill_documents WHERE contract_id = ? OR id = ? ORDER BY document_date DESC', [$id, (int) ($contract['source_document_id'] ?? 0)]);
        $source = $contract['source_document_id'] ? Database::one('SELECT * FROM easybill_documents WHERE id = ?', [$contract['source_document_id']]) : null;
        $notes = Database::all('SELECT n.*, u.name AS user_name FROM notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.contract_id = ? ORDER BY n.created_at DESC', [$id]);
        $tasks = Database::all('SELECT a.*, u.name AS user_name FROM appointments a LEFT JOIN users u ON u.id = a.assigned_to WHERE a.contract_id = ? ORDER BY a.done_at IS NOT NULL, a.due_at', [$id]);
        $invoiced = (int) Database::value("SELECT COALESCE(SUM(CASE WHEN type='gutschrift' THEN -amount_net_cents ELSE amount_net_cents END),0) FROM easybill_documents WHERE contract_id = ? AND type IN ('rechnung','gutschrift') AND status <> 'storniert' AND is_draft = 0", [$id]);
        $users = Database::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name');
        $this->view('contracts/show', compact('contract', 'documents', 'source', 'notes', 'tasks', 'invoiced', 'users') + ['pageTitle' => $contract['title']]);
    }

    public function edit(int $id): void
    {
        $contract = Database::one('SELECT * FROM contracts WHERE id = ?', [$id]) ?? $this->notFound('Vertrag');
        $this->view('contracts/form', ['pageTitle' => 'Vertrag bearbeiten', 'contract' => $contract, 'customers' => $this->customers()]);
    }

    public function update(int $id): void
    {
        $contract = Database::one('SELECT * FROM contracts WHERE id = ?', [$id]) ?? $this->notFound('Vertrag');
        [$data, $errors] = ContractService::fromInput($_POST);
        if ($errors) {
            $this->failValidation($errors, '/vertraege/' . $id . '/bearbeiten');
        }
        if ($data['status'] === 'gekuendigt' && $contract['status'] !== 'gekuendigt') {
            $data['cancelled_at'] = date('Y-m-d');
        } elseif ($data['status'] !== 'gekuendigt') {
            $data['cancelled_at'] = null;
        }
        Database::update('contracts', $id, $data);
        if (in_array($data['status'], ['aktiv', 'gekuendigt'], true)) {
            Database::run("UPDATE customers SET status = 'kunde' WHERE id = ? AND status = 'interessent'", [$data['customer_id']]);
        }
        flash('success', 'Vertrag gespeichert.');
        redirect('/vertraege/' . $id);
    }

    public function cancel(int $id): void
    {
        $contract = Database::one('SELECT * FROM contracts WHERE id = ?', [$id]) ?? $this->notFound('Vertrag');
        $end = parse_date_de((string) ($_POST['end_date'] ?? ''));
        if ($end === null) {
            flash('error', 'Bitte ein gültiges Vertragsende (TT.MM.JJJJ) angeben.');
            redirect('/vertraege/' . $id);
        }
        if ($end < $contract['start_date']) {
            flash('error', 'Das Vertragsende darf nicht vor dem Start liegen.');
            redirect('/vertraege/' . $id);
        }
        Database::update('contracts', $id, ['status' => 'gekuendigt', 'end_date' => $end, 'cancelled_at' => date('Y-m-d')]);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        Database::insert('notes', [
            'customer_id' => $contract['customer_id'], 'contract_id' => $id, 'user_id' => Auth::id(),
            'body' => 'Vertrag gekündigt zum ' . date_de($end) . ($reason !== '' ? '. Grund: ' . $reason : '.'),
        ]);
        flash('success', 'Vertrag wurde zum ' . date_de($end) . ' gekündigt.');
        redirect('/vertraege/' . $id);
    }

    public function destroy(int $id): void
    {
        $contract = Database::one('SELECT * FROM contracts WHERE id = ?', [$id]) ?? $this->notFound('Vertrag');
        Database::transaction(function () use ($id) {
            Database::run('UPDATE easybill_documents SET contract_id = NULL WHERE contract_id = ?', [$id]);
            Database::run('DELETE FROM contracts WHERE id = ?', [$id]);
        });
        flash('success', 'Vertrag „' . $contract['title'] . '“ wurde gelöscht.');
        redirect('/kunden/' . $contract['customer_id']);
    }

    private function customers(): array
    {
        return Database::all("SELECT id, name, status FROM customers ORDER BY status = 'inaktiv', name");
    }
}
