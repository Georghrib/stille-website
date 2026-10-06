<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class SearchController extends Controller
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $customers = $contracts = $documents = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            $customers = Database::all(
                'SELECT * FROM customers WHERE name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR city LIKE ? ORDER BY name LIMIT 20',
                [$like, $like, $like, $like]
            );
            $contracts = Database::all(
                'SELECT c.*, cu.name AS customer_name FROM contracts c JOIN customers cu ON cu.id = c.customer_id WHERE c.title LIKE ? OR cu.name LIKE ? ORDER BY c.start_date DESC LIMIT 20',
                [$like, $like]
            );
            $documents = Database::all(
                'SELECT * FROM easybill_documents WHERE number LIKE ? OR title LIKE ? OR customer_name LIKE ? ORDER BY document_date DESC LIMIT 20',
                [$like, $like, $like]
            );
        }
        $this->view('search/index', compact('q', 'customers', 'contracts', 'documents') + ['pageTitle' => 'Suche']);
    }
}
