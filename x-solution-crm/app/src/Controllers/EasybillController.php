<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;
use App\Services\ContractService;
use App\Services\EasybillClient;
use App\Services\EasybillSync;

final class EasybillController extends Controller
{
    private const TABS = [
        'posteingang' => "(d.inbox_state = 'neu' OR d.suggestion IS NOT NULL)",
        'zugeordnet' => "d.inbox_state IN ('zugeordnet','interessent','vertrag')",
        'abgelegt' => "d.inbox_state = 'abgelegt'",
        'alle' => '1=1',
    ];

    public function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'posteingang');
        if (!isset(self::TABS[$tab])) {
            $tab = 'posteingang';
        }
        $type = (string) ($_GET['typ'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        [$offset, $page, $per] = $this->paging(30);

        $where = [self::TABS[$tab]];
        $params = [];
        if (in_array($type, ['angebot', 'rechnung', 'gutschrift'], true)) {
            $where[] = 'd.type = :type';
            $params['type'] = $type;
        }
        if ($q !== '') {
            $where[] = '(d.number LIKE :q OR d.title LIKE :q2 OR d.customer_name LIKE :q3 OR c.name LIKE :q4)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM easybill_documents d LEFT JOIN customers c ON c.id = d.customer_id WHERE $w", $params);
        $rows = Database::all(
            "SELECT d.*, c.name AS crm_customer FROM easybill_documents d LEFT JOIN customers c ON c.id = d.customer_id
             WHERE $w ORDER BY d.suggestion IS NULL, d.document_date DESC, d.id DESC LIMIT $per OFFSET $offset",
            $params
        );
        $counts = [];
        foreach (self::TABS as $k => $cond) {
            $counts[$k] = (int) Database::value("SELECT COUNT(*) FROM easybill_documents d WHERE $cond");
        }
        $lastSync = Settings::get('easybill_last_sync');
        $lastCron = Settings::get('cron_last_run');
        $apiConfigured = env('EASYBILL_API_KEY') !== null;
        $this->view('easybill/index', compact('rows', 'tab', 'type', 'q', 'counts', 'total', 'page', 'per', 'lastSync', 'lastCron', 'apiConfigured') + ['pageTitle' => 'Aus easybill']);
    }

    public function show(int $id): void
    {
        $doc = $this->find($id);
        $customer = $doc['customer_id'] ? Database::one('SELECT * FROM customers WHERE id = ?', [$doc['customer_id']]) : null;
        $contract = $doc['contract_id'] ? Database::one('SELECT * FROM contracts WHERE id = ?', [$doc['contract_id']]) : null;
        $offer = $doc['suggestion_ref_id'] ? Database::one('SELECT * FROM easybill_documents WHERE id = ?', [$doc['suggestion_ref_id']]) : null;
        $offerContract = $offer && $offer['contract_id'] ? Database::one('SELECT * FROM contracts WHERE id = ?', [$offer['contract_id']]) : null;
        $related = Database::all('SELECT * FROM easybill_documents WHERE id <> ? AND (easybill_id = ? OR ref_easybill_id = ?)', [$id, (int) $doc['ref_easybill_id'], (int) $doc['easybill_id']]);
        $customers = Database::all("SELECT id, name, status, email FROM customers ORDER BY status = 'inaktiv', name");
        $suggestedCustomer = $customer['id'] ?? ($offer['customer_id'] ?? null);
        $this->view('easybill/show', [
            'doc' => $doc,
            'customer' => $customer,
            'contract' => $contract,
            'offer' => $offer,
            'offerContract' => $offerContract,
            'related' => $related,
            'customers' => $customers,
            'suggestedCustomer' => $suggestedCustomer,
            'prefillCustomer' => self::prefillCustomer($doc),
            'prefillContract' => $offerContract ?: self::prefillContract($doc, $offer),
            'raw' => json_decode((string) $doc['raw_payload'], true),
            'pageTitle' => doc_type_label($doc['type']) . ' ' . ($doc['number'] ?? ''),
        ]);
    }

    /** Übernahmedialog: Interessent | Vertrag | Nur ablegen */
    public function accept(int $id): void
    {
        $doc = $this->find($id);
        $mode = (string) ($_POST['mode'] ?? '');
        if (!in_array($mode, ['interessent', 'vertrag', 'ablegen'], true)) {
            flash('error', 'Bitte eine Übernahme-Option wählen.');
            redirect('/easybill/' . $id);
        }

        if ($mode === 'ablegen') {
            Database::update('easybill_documents', $id, ['inbox_state' => 'abgelegt', 'suggestion' => null]);
            flash('success', doc_type_label($doc['type']) . ' ' . $doc['number'] . ' wurde abgelegt.');
            redirect('/easybill');
        }

        $contractData = null;
        if ($mode === 'vertrag') {
            [$contractData, $errors] = ContractService::fromInput($_POST, false);
            if ($errors) {
                $this->failValidation($errors, '/easybill/' . $id);
            }
        }
        [$customerId, $customerErrors] = $this->resolveCustomer($doc, $mode === 'vertrag' ? 'kunde' : 'interessent');
        if ($customerErrors) {
            $this->failValidation($customerErrors, '/easybill/' . $id);
        }

        $contractId = Database::transaction(function () use ($doc, $id, $mode, $customerId, $contractData) {
            $customerId = $customerId ?? $this->createCustomer($doc, $mode === 'vertrag' ? 'kunde' : 'interessent');
            $update = ['customer_id' => $customerId, 'inbox_state' => $mode, 'suggestion' => null];
            $contractId = null;
            if ($mode === 'vertrag') {
                $contractId = Database::insert('contracts', $contractData + [
                    'customer_id' => $customerId, 'source_document_id' => $id, 'created_by' => Auth::id(),
                    'cancelled_at' => $contractData['status'] === 'gekuendigt' ? date('Y-m-d') : null,
                ]);
                $update['contract_id'] = $contractId;
                if (in_array($contractData['status'], ['aktiv', 'gekuendigt'], true)) {
                    Database::run("UPDATE customers SET status = 'kunde' WHERE id = ?", [$customerId]);
                }
            }
            Database::update('easybill_documents', $id, $update);
            $this->linkEasybillCustomer($customerId, $doc['easybill_customer_id']);
            Database::insert('notes', [
                'customer_id' => $customerId, 'contract_id' => $contractId, 'user_id' => Auth::id(),
                'body' => 'Aus easybill übernommen: ' . doc_type_label($doc['type']) . ' ' . ($doc['number'] ?? '#' . $doc['easybill_id'])
                    . ' (' . money($doc['amount_net_cents']) . ' netto)' . ($mode === 'vertrag' ? ' → Vertrag angelegt.' : ' → als Interessent.'),
            ]);
            return $contractId;
        });

        if ($mode === 'vertrag') {
            flash('success', 'Vertrag „' . $contractData['title'] . '“ wurde aus ' . doc_type_label($doc['type']) . ' ' . $doc['number'] . ' angelegt.');
            redirect('/vertraege/' . $contractId);
        }
        flash('success', doc_type_label($doc['type']) . ' ' . $doc['number'] . ' wurde als Interessent übernommen.');
        redirect('/kunden/' . Database::value('SELECT customer_id FROM easybill_documents WHERE id = ?', [$id]));
    }

    /** Bestätigte Umstellung: Angebot wurde in easybill zur Rechnung. */
    public function convert(int $id): void
    {
        $invoice = $this->find($id);
        if ($invoice['suggestion'] !== 'umstellung') {
            flash('error', 'Für diesen Beleg liegt kein Umstellungsvorschlag vor.');
            redirect('/easybill/' . $id);
        }
        $offer = $invoice['suggestion_ref_id'] ? Database::one('SELECT * FROM easybill_documents WHERE id = ?', [$invoice['suggestion_ref_id']]) : null;
        [$contractData, $errors] = ContractService::fromInput($_POST, false);
        if ($errors) {
            $this->failValidation($errors, '/easybill/' . $id);
        }
        [$customerId, $customerErrors] = $this->resolveCustomer($invoice, 'kunde');
        if ($customerErrors) {
            $this->failValidation($customerErrors, '/easybill/' . $id);
        }
        $contractData['status'] = 'aktiv';

        $contractId = Database::transaction(function () use ($invoice, $offer, $id, $customerId, $contractData) {
            $customerId = $customerId ?? $offer['customer_id'] ?? $this->createCustomer($invoice, 'kunde');
            Database::run("UPDATE customers SET status = 'kunde' WHERE id = ?", [$customerId]);

            $existing = $offer && $offer['contract_id'] ? Database::one('SELECT * FROM contracts WHERE id = ?', [$offer['contract_id']]) : null;
            if ($existing && $existing['status'] !== 'beendet') {
                Database::update('contracts', (int) $existing['id'], $contractData + ['customer_id' => $customerId, 'cancelled_at' => null]);
                $contractId = (int) $existing['id'];
            } else {
                $contractId = Database::insert('contracts', $contractData + [
                    'customer_id' => $customerId, 'source_document_id' => $offer['id'] ?? $id, 'created_by' => Auth::id(),
                ]);
            }
            Database::update('easybill_documents', $id, ['customer_id' => $customerId, 'contract_id' => $contractId, 'inbox_state' => 'vertrag', 'suggestion' => null]);
            if ($offer) {
                Database::update('easybill_documents', (int) $offer['id'], [
                    'customer_id' => $customerId, 'contract_id' => $contractId, 'inbox_state' => 'vertrag', 'suggestion' => null,
                    'status' => $offer['status'] === 'offen' ? 'angenommen' : $offer['status'],
                ]);
            }
            $this->linkEasybillCustomer($customerId, $invoice['easybill_customer_id']);
            Database::insert('notes', [
                'customer_id' => $customerId, 'contract_id' => $contractId, 'user_id' => Auth::id(),
                'body' => 'Umstellung bestätigt: Angebot ' . ($offer['number'] ?? '?') . ' wurde in easybill zur Rechnung ' . $invoice['number'] . '. Kunde aktiviert, Vertrag ' . ($existing ? 'aktiviert' : 'angelegt') . '.',
            ]);
            return $contractId;
        });
        flash('success', 'Umstellung durchgeführt: Kunde ist aktiv, Vertrag „' . $contractData['title'] . '“ läuft.');
        redirect('/vertraege/' . $contractId);
    }

    public function dismissSuggestion(int $id): void
    {
        $this->find($id);
        Database::update('easybill_documents', $id, ['suggestion' => null]);
        flash('success', 'Vorschlag verworfen. Der Beleg bleibt unverändert.');
        redirect('/easybill/' . $id);
    }

    public function reset(int $id): void
    {
        $doc = $this->find($id);
        if ($doc['contract_id']) {
            flash('error', 'Der Beleg ist mit einem Vertrag verknüpft und kann nicht zurückgesetzt werden.');
            redirect('/easybill/' . $id);
        }
        Database::update('easybill_documents', $id, ['inbox_state' => $doc['customer_id'] ? 'zugeordnet' : 'neu']);
        flash('success', 'Beleg wurde zurück in den Posteingang gelegt.');
        redirect('/easybill/' . $id);
    }

    /** PDF nur für angemeldete Benutzer (Datei liegt außerhalb von public_html). */
    public function pdf(int $id): void
    {
        $doc = $this->find($id);
        if (!$doc['pdf_path'] && ($client = EasybillClient::fromEnv())) {
            try {
                (new EasybillSync($client))->fetchPdf($doc);
                $doc = $this->find($id);
            } catch (\Throwable $e) {
                flash('error', 'PDF konnte nicht geladen werden: ' . $e->getMessage());
                redirect('/easybill/' . $id);
            }
        }
        $base = realpath(STORAGE_PATH . '/pdfs');
        $file = $doc['pdf_path'] ? realpath(STORAGE_PATH . '/' . $doc['pdf_path']) : false;
        if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            $this->notFound('PDF');
        }
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($doc['number'] ?: 'beleg-' . $doc['easybill_id'])) . '.pdf';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /** Manueller Abgleich (gleiche Logik wie cron.php). */
    public function sync(): void
    {
        $client = EasybillClient::fromEnv();
        if (!$client) {
            flash('error', 'Kein easybill-API-Key hinterlegt (Einstellungen → easybill).');
            redirect('/easybill');
        }
        $sync = new EasybillSync($client);
        try {
            $w = $sync->processPendingWebhooks();
            $k = $sync->syncCustomers(true);
            $s = $sync->syncChanged();
            $p = $sync->downloadMissingPdfs(5);
            flash('success', sprintf('Abgleich abgeschlossen: %d Belege geprüft, %d neu, %d aktualisiert · %d Kontakte (%d neu) · %d Webhooks nachgeholt · %d PDFs geladen.', $s['geprueft'], $s['neu'], $s['aktualisiert'], $k['geprueft'] ?? 0, $k['neu'] ?? 0, $w['ok'], $p));
        } catch (\Throwable $e) {
            flash('error', 'Abgleich fehlgeschlagen: ' . $e->getMessage());
        }
        redirect('/easybill');
    }

    /* ---------------- Hilfen ---------------- */

    private function find(int $id): array
    {
        return Database::one('SELECT * FROM easybill_documents WHERE id = ?', [$id]) ?? $this->notFound('Beleg');
    }

    /**
     * Gewählter bestehender Kunde (ID) oder null = neu anlegen (Felder werden geprüft).
     * @return array{0: ?int, 1: array<string,string>}
     */
    private function resolveCustomer(array $doc, string $targetStatus): array
    {
        $choice = (string) ($_POST['customer_choice'] ?? 'neu');
        if ($choice !== 'neu') {
            $cust = Database::one('SELECT * FROM customers WHERE id = ?', [(int) $choice]);
            if (!$cust) {
                return [null, ['customer_choice' => 'Der gewählte Kunde existiert nicht.']];
            }
            if ($targetStatus === 'interessent' && $cust['status'] === 'inaktiv') {
                Database::run("UPDATE customers SET status = 'interessent' WHERE id = ?", [$cust['id']]);
            }
            return [(int) $cust['id'], []];
        }
        $name = trim((string) ($_POST['customer_name'] ?? ''));
        $email = trim((string) ($_POST['customer_email'] ?? ''));
        $errors = [];
        if ($name === '') {
            $errors['customer_name'] = 'Bitte einen Kundennamen angeben.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'Die E-Mail-Adresse ist ungültig.';
        }
        return [null, $errors];
    }

    private function createCustomer(array $doc, string $status): int
    {
        $p = self::prefillCustomer($doc);
        $ebId = $doc['easybill_customer_id'] && !Database::value('SELECT id FROM customers WHERE easybill_customer_id = ?', [$doc['easybill_customer_id']])
            ? (int) $doc['easybill_customer_id'] : null;
        return Database::insert('customers', [
            'status' => $status,
            'name' => mb_substr(trim((string) ($_POST['customer_name'] ?? $p['name'])), 0, 190),
            'contact_person' => trim((string) ($_POST['customer_contact'] ?? $p['contact_person'])) ?: null,
            'email' => mb_strtolower(trim((string) ($_POST['customer_email'] ?? $p['email']))) ?: null,
            'phone' => $p['phone'] ?: null,
            'street' => $p['street'] ?: null,
            'zip' => $p['zip'] ?: null,
            'city' => $p['city'] ?: null,
            'country' => $p['country'] ?: 'AT',
            'vat_id' => $p['vat_id'] ?: null,
            'easybill_customer_id' => $ebId,
        ]);
    }

    private function linkEasybillCustomer(int $customerId, mixed $ebCustomerId): void
    {
        if (!$ebCustomerId) {
            return;
        }
        $taken = Database::value('SELECT id FROM customers WHERE easybill_customer_id = ?', [(int) $ebCustomerId]);
        if (!$taken) {
            Database::run('UPDATE customers SET easybill_customer_id = ? WHERE id = ? AND easybill_customer_id IS NULL', [(int) $ebCustomerId, $customerId]);
        }
        // Weitere Belege desselben easybill-Kunden automatisch zuordnen
        Database::run("UPDATE easybill_documents SET customer_id = ?, inbox_state = IF(inbox_state = 'neu', 'zugeordnet', inbox_state) WHERE customer_id IS NULL AND easybill_customer_id = ? AND suggestion IS NULL", [$customerId, (int) $ebCustomerId]);
    }

    /** Kundendaten aus dem gespeicherten easybill-Payload. */
    public static function prefillCustomer(array $doc): array
    {
        $raw = json_decode((string) $doc['raw_payload'], true) ?: [];
        $s = (array) ($raw['customer_snapshot'] ?? []) + (array) ($raw['address'] ?? []);
        $person = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
        return [
            'name' => $doc['customer_name'] ?: (EasybillSync::customerName($s) ?? ''),
            'contact_person' => !empty($s['company_name']) && $person !== '' ? $person : '',
            'email' => $doc['customer_email'] ?? '',
            'phone' => (string) ($s['phone_1'] ?? $s['mobile'] ?? ''),
            'street' => (string) ($s['street'] ?? ''),
            'zip' => (string) ($s['zip_code'] ?? $s['zipcode'] ?? ''),
            'city' => (string) ($s['city'] ?? ''),
            'country' => strtoupper(substr((string) ($s['country'] ?? 'AT'), 0, 2)) ?: 'AT',
            'vat_id' => (string) ($s['vat_identifier'] ?? $raw['vat_id'] ?? ''),
        ];
    }

    /** Vorschlag für die Vertragsfelder aus dem Beleg. */
    public static function prefillContract(array $doc, ?array $offer = null): array
    {
        $base = $offer ?: $doc;
        $start = $doc['document_date'] ?: date('Y-m-d');
        $end = date('Y-m-d', strtotime($start . ' +12 months -1 day'));
        return [
            'title' => $base['title'] ?: doc_type_label($base['type']) . ' ' . $base['number'],
            'status' => 'aktiv',
            'billing_interval' => 'monatlich',
            'total_value_cents' => (int) $base['amount_net_cents'],
            'monthly_value_cents' => null,
            'start_date' => $start,
            'end_date' => $end,
            'notice_days' => 90,
        ];
    }
}
