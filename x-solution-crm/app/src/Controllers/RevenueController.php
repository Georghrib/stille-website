<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Period;
use App\Services\Stats;

final class RevenueController extends Controller
{
    public function index(): void
    {
        $year = $this->year();
        $from = $year . '-01';
        $to = $year . '-12';
        $invoiced = Stats::revenueByMonth($from, $to);
        $prev = Stats::revenueByMonth(($year - 1) . '-01', ($year - 1) . '-12');
        $basis = Stats::contractBasisByMonth($from, $to);

        $detail = [];
        foreach (Database::all(
            "SELECT DATE_FORMAT(document_date, '%Y-%m') ym,
                    SUM(CASE WHEN type='rechnung' THEN amount_net_cents ELSE 0 END) inv,
                    SUM(CASE WHEN type='gutschrift' THEN amount_net_cents ELSE 0 END) cred,
                    SUM(type='rechnung') n,
                    SUM(CASE WHEN type='rechnung' AND paid_at IS NULL THEN amount_gross_cents ELSE 0 END) open_gross
             FROM easybill_documents
             WHERE type IN ('rechnung','gutschrift') AND status <> 'storniert' AND is_draft = 0 AND YEAR(document_date) = ?
             GROUP BY ym",
            [$year]
        ) as $r) {
            $detail[$r['ym']] = $r;
        }

        $chart = [
            'labels' => array_map(fn ($ym) => MONTHS_DE_SHORT[(int) substr($ym, 5, 2)], array_keys($invoiced)),
            'invoiced' => array_map(fn ($c) => round($c / 100, 2), array_values($invoiced)),
            'contracts' => array_map(fn ($c) => round($c / 100, 2), array_values($basis)),
        ];

        $sumNow = array_sum($invoiced);
        // Vorjahresvergleich im laufenden Jahr nur bis zum aktuellen Monat
        $cut = $year === (int) date('Y') ? (int) date('n') : 12;
        $sumPrev = array_sum(array_slice($prev, 0, $cut));
        $sumNowCut = array_sum(array_slice($invoiced, 0, $cut));

        $top = Database::all(
            "SELECT c.id, c.name, SUM(CASE WHEN d.type='gutschrift' THEN -d.amount_net_cents ELSE d.amount_net_cents END) cents, COUNT(*) n
             FROM easybill_documents d JOIN customers c ON c.id = d.customer_id
             WHERE d.type IN ('rechnung','gutschrift') AND d.status <> 'storniert' AND d.is_draft = 0 AND YEAR(d.document_date) = ?
             GROUP BY c.id, c.name ORDER BY cents DESC LIMIT 8",
            [$year]
        );
        $open = Database::all(
            "SELECT d.*, COALESCE(c.name, d.customer_name) display_name FROM easybill_documents d LEFT JOIN customers c ON c.id = d.customer_id
             WHERE d.type = 'rechnung' AND d.paid_at IS NULL AND d.status IN ('offen','ueberfaellig') AND d.is_draft = 0
             ORDER BY d.due_date ASC LIMIT 15"
        );
        $openSum = (int) Database::value("SELECT COALESCE(SUM(amount_gross_cents),0) FROM easybill_documents WHERE type='rechnung' AND paid_at IS NULL AND status IN ('offen','ueberfaellig') AND is_draft = 0");
        $years = range((int) date('Y'), max((int) date('Y') - 6, (int) (Database::value('SELECT MIN(YEAR(document_date)) FROM easybill_documents') ?: date('Y'))));
        $mrr = $basis[$year === (int) date('Y') ? date('Y-m') : $year . '-12'] ?? 0;

        $this->view('revenue/index', compact('year', 'years', 'invoiced', 'basis', 'detail', 'chart', 'sumNow', 'sumPrev', 'sumNowCut', 'cut', 'top', 'open', 'openSum', 'mrr')
            + ['pageTitle' => 'Umsatz', 'useCharts' => true]);
    }

    /** CSV-Export (Excel-kompatibel: Semikolon, UTF-8 mit BOM, Dezimalkomma). */
    public function export(): void
    {
        $year = $this->year();
        $kind = ($_GET['art'] ?? 'belege') === 'monate' ? 'monate' : 'belege';
        $filename = 'umsatz-' . $year . '-' . $kind . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $num = fn (int $cents) => number_format($cents / 100, 2, ',', '');
        $csv = fn (array $row) => fputcsv($out, array_map(fn ($v) => self::csvSafe((string) $v), $row), ';', '"', '');

        if ($kind === 'monate') {
            $invoiced = Stats::revenueByMonth($year . '-01', $year . '-12');
            $basis = Stats::contractBasisByMonth($year . '-01', $year . '-12');
            $csv(['Monat', 'Umsatz netto (Rechnungen - Gutschriften) EUR', 'Vertragsbasis (MRR) EUR']);
            foreach ($invoiced as $ym => $cents) {
                $csv([month_label($ym), $num($cents), $num($basis[$ym] ?? 0)]);
            }
            $csv(['Summe ' . $year, $num(array_sum($invoiced)), '']);
        } else {
            $csv(['Belegdatum', 'Typ', 'Nummer', 'Kunde', 'Titel', 'Netto EUR', 'Brutto EUR', 'Status', 'Fällig am', 'Bezahlt am', 'Vertrag', 'easybill-ID']);
            $rows = Database::run(
                "SELECT d.*, COALESCE(c.name, d.customer_name) display_name, k.title contract_title
                 FROM easybill_documents d LEFT JOIN customers c ON c.id = d.customer_id LEFT JOIN contracts k ON k.id = d.contract_id
                 WHERE d.type IN ('rechnung','gutschrift') AND d.is_draft = 0 AND YEAR(d.document_date) = ?
                 ORDER BY d.document_date, d.id",
                [$year]
            );
            while ($d = $rows->fetch()) {
                $sign = $d['type'] === 'gutschrift' ? -1 : 1;
                $csv([
                    date_de($d['document_date']), doc_type_label($d['type']), $d['number'], $d['display_name'], $d['title'],
                    $num($sign * (int) $d['amount_net_cents']), $num($sign * (int) $d['amount_gross_cents']),
                    doc_status_label($d['status']), $d['due_date'] ? date_de($d['due_date']) : '', $d['paid_at'] ? date_de($d['paid_at']) : '',
                    $d['contract_title'] ?? '', $d['easybill_id'],
                ]);
            }
        }
        fclose($out);
        exit;
    }

    /** Schutz vor CSV-/Formel-Injection in Excel. */
    private static function csvSafe(string $v): string
    {
        return ($v !== '' && in_array($v[0], ['=', '+', '@', "\t", "\r"], true)) || (str_starts_with($v, '-') && !is_numeric(str_replace(',', '.', $v)))
            ? "'" . $v : $v;
    }

    private function year(): int
    {
        $y = (int) ($_GET['jahr'] ?? substr(Period::current(), 0, 4));
        return ($y >= 2000 && $y <= (int) date('Y') + 1) ? $y : (int) date('Y');
    }
}
