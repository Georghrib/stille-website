<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Services\CustomerLogo;

final class CustomerController extends Controller
{
    private const STATUSES = ['interessent', 'kunde', 'inaktiv'];

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? '');
        [$offset, $page, $per] = $this->paging(25);

        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(cu.name LIKE :q OR cu.contact_person LIKE :q2 OR cu.email LIKE :q3 OR cu.city LIKE :q4)';
            $like = '%' . $q . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'cu.status = :status';
            $params['status'] = $status;
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM customers cu WHERE $w", $params);
        $rows = Database::all(
            "SELECT cu.*,
                (SELECT COUNT(*) FROM contracts c WHERE c.customer_id = cu.id AND c.status IN ('aktiv','gekuendigt')) AS active_contracts,
                (SELECT COALESCE(SUM(c.monthly_value_cents),0) * 12 FROM contracts c WHERE c.customer_id = cu.id AND c.status IN ('aktiv','gekuendigt') AND c.billing_interval <> 'einmalig') AS annual_cents,
                (SELECT COALESCE(SUM(CASE WHEN d.type = 'gutschrift' THEN -d.amount_net_cents ELSE d.amount_net_cents END),0)
                   FROM easybill_documents d WHERE d.customer_id = cu.id AND d.type IN ('rechnung','gutschrift') AND d.status <> 'storniert' AND d.is_draft = 0) AS revenue_cents
             FROM customers cu WHERE $w ORDER BY cu.name ASC LIMIT $per OFFSET $offset",
            $params
        );
        $counts = ['' => 0] + array_fill_keys(self::STATUSES, 0);
        foreach (Database::all('SELECT status, COUNT(*) n FROM customers GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
            $counts[''] += (int) $r['n'];
        }
        $this->view('customers/index', compact('rows', 'q', 'status', 'counts', 'total', 'page', 'per') + ['pageTitle' => 'Kunden']);
    }

    public function create(): void
    {
        $this->view('customers/form', ['pageTitle' => 'Neuer Kunde', 'customer' => null]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate();
        if ($errors) {
            $this->failValidation($errors, '/kunden/neu');
        }
        $id = Database::insert('customers', $data);
        flash('success', 'Kunde „' . $data['name'] . '“ wurde angelegt.');
        redirect('/kunden/' . $id);
    }

    public function show(int $id): void
    {
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]) ?? $this->notFound('Kunde');
        $contracts = Database::all("SELECT * FROM contracts WHERE customer_id = ? ORDER BY FIELD(status, 'aktiv','gekuendigt','entwurf','beendet'), start_date DESC", [$id]);
        $documents = Database::all('SELECT * FROM easybill_documents WHERE customer_id = ? ORDER BY document_date DESC, id DESC LIMIT 50', [$id]);
        $notes = Database::all('SELECT n.*, u.name AS user_name, c.title AS contract_title FROM notes n LEFT JOIN users u ON u.id = n.user_id LEFT JOIN contracts c ON c.id = n.contract_id WHERE n.customer_id = ? ORDER BY n.created_at DESC', [$id]);
        $tasks = Database::all('SELECT a.*, u.name AS user_name FROM appointments a LEFT JOIN users u ON u.id = a.assigned_to WHERE a.customer_id = ? ORDER BY a.done_at IS NOT NULL, a.due_at ASC LIMIT 20', [$id]);
        $stats = Database::one(
            "SELECT
               COALESCE(SUM(CASE WHEN type='rechnung' AND status <> 'storniert' AND is_draft = 0 THEN amount_net_cents WHEN type='gutschrift' THEN -amount_net_cents ELSE 0 END),0) AS revenue,
               COALESCE(SUM(CASE WHEN type='rechnung' AND paid_at IS NULL AND status NOT IN ('storniert','bezahlt') AND is_draft = 0 THEN amount_gross_cents ELSE 0 END),0) AS open_amount,
               COALESCE(SUM(CASE WHEN type='angebot' AND status = 'offen' THEN amount_net_cents ELSE 0 END),0) AS offers
             FROM easybill_documents WHERE customer_id = ?",
            [$id]
        );
        $annual = 0;
        foreach ($contracts as $c) {
            if (in_array($c['status'], ['aktiv', 'gekuendigt'], true) && $c['billing_interval'] !== 'einmalig') {
                $annual += (int) $c['monthly_value_cents'] * 12;
            }
        }
        $users = Database::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name');
        $this->view('customers/show', compact('customer', 'contracts', 'documents', 'notes', 'tasks', 'stats', 'annual', 'users') + ['pageTitle' => $customer['name']]);
    }

    public function edit(int $id): void
    {
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]) ?? $this->notFound('Kunde');
        $this->view('customers/form', ['pageTitle' => 'Kunde bearbeiten', 'customer' => $customer]);
    }

    public function update(int $id): void
    {
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]) ?? $this->notFound('Kunde');
        [$data, $errors] = $this->validate($id);
        if ($errors) {
            $this->failValidation($errors, '/kunden/' . $id . '/bearbeiten');
        }
        Database::update('customers', $id, $data);
        flash('success', 'Änderungen an „' . $data['name'] . '“ gespeichert.');
        redirect('/kunden/' . $customer['id']);
    }

    public function destroy(int $id): void
    {
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]) ?? $this->notFound('Kunde');
        if (!Auth::isAdmin()) {
            flash('error', 'Nur Administratoren dürfen Kunden löschen. Tipp: Status auf „Inaktiv“ setzen.');
            redirect('/kunden/' . $id);
        }
        Database::transaction(function () use ($id) {
            // Belege bleiben erhalten und wandern zurück in den Posteingang
            Database::run("UPDATE easybill_documents SET customer_id = NULL, contract_id = NULL, inbox_state = 'neu', suggestion = NULL WHERE customer_id = ?", [$id]);
            Database::run('DELETE FROM customers WHERE id = ?', [$id]);
        });
        CustomerLogo::remove($id);
        flash('success', 'Kunde „' . $customer['name'] . '“ wurde gelöscht. Zugehörige easybill-Belege liegen wieder im Posteingang.');
        redirect('/kunden');
    }

    public function logo(int $id): void
    {
        CustomerLogo::serve($id);
    }

    public function uploadLogo(int $id): void
    {
        Database::one('SELECT id FROM customers WHERE id = ?', [$id]) ?? $this->notFound('Kunde');
        try {
            if (!empty($_POST['remove_logo'])) {
                CustomerLogo::remove($id);
                flash('success', 'Logo entfernt.');
            } else {
                CustomerLogo::store($id, $_FILES['logo'] ?? []);
                flash('success', 'Logo gespeichert.');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/kunden/' . $id);
    }

    /** @return array{0: array<string,mixed>, 1: array<string,string>} */
    private function validate(?int $id = null): array
    {
        $errors = [];
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'contact_person' => trim((string) ($_POST['contact_person'] ?? '')) ?: null,
            'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'street' => trim((string) ($_POST['street'] ?? '')) ?: null,
            'zip' => trim((string) ($_POST['zip'] ?? '')) ?: null,
            'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
            'country' => strtoupper(trim((string) ($_POST['country'] ?? 'AT'))) ?: 'AT',
            'vat_id' => trim((string) ($_POST['vat_id'] ?? '')) ?: null,
            'status' => (string) ($_POST['status'] ?? 'interessent'),
        ];
        if ($data['name'] === '' || mb_strlen($data['name']) > 190) {
            $errors['name'] = 'Bitte einen Namen (max. 190 Zeichen) angeben.';
        }
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Die E-Mail-Adresse ist ungültig.';
        }
        if (!preg_match('/^[A-Z]{2}$/', $data['country'])) {
            $errors['country'] = 'Land bitte als zweistelligen Code (z. B. AT, DE).';
        }
        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors['status'] = 'Ungültiger Status.';
        }
        $eb = trim((string) ($_POST['easybill_customer_id'] ?? ''));
        if ($eb !== '') {
            if (!ctype_digit($eb)) {
                $errors['easybill_customer_id'] = 'Die easybill-Kunden-ID muss eine Zahl sein.';
            } elseif (Database::value('SELECT id FROM customers WHERE easybill_customer_id = ? AND id <> ?', [(int) $eb, $id ?? 0])) {
                $errors['easybill_customer_id'] = 'Diese easybill-Kunden-ID ist bereits einem anderen Kunden zugeordnet.';
            }
        }
        $data['easybill_customer_id'] = $eb === '' ? null : (int) $eb;
        return [$data, $errors];
    }
}
