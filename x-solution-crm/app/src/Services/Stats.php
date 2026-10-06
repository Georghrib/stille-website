<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Kennzahlen für Dashboard und Umsatzauswertung.
 * Umsatz = Rechnungen (netto) minus Gutschriften, ohne Entwürfe und stornierte Belege.
 */
final class Stats
{
    private const REVENUE_SQL = "CASE WHEN type = 'gutschrift' THEN -amount_net_cents ELSE amount_net_cents END";
    private const REVENUE_WHERE = "type IN ('rechnung','gutschrift') AND status <> 'storniert' AND is_draft = 0";

    /** @return array<string,int> Y-m => Cent, lückenlos von $fromYm bis $toYm */
    public static function revenueByMonth(string $fromYm, string $toYm): array
    {
        $rows = Database::all(
            'SELECT DATE_FORMAT(document_date, \'%Y-%m\') AS ym, SUM(' . self::REVENUE_SQL . ') AS cents
             FROM easybill_documents WHERE ' . self::REVENUE_WHERE . ' AND document_date BETWEEN :a AND :b GROUP BY ym',
            ['a' => $fromYm . '-01', 'b' => Period::lastDay($toYm)]
        );
        $map = array_column($rows, 'cents', 'ym');
        $out = [];
        for ($ym = $fromYm; $ym <= $toYm; $ym = Period::shift($ym, 1)) {
            $out[$ym] = (int) ($map[$ym] ?? 0);
        }
        return $out;
    }

    public static function revenueBetween(string $from, string $to): int
    {
        return (int) Database::value(
            'SELECT COALESCE(SUM(' . self::REVENUE_SQL . '),0) FROM easybill_documents WHERE ' . self::REVENUE_WHERE . ' AND document_date BETWEEN ? AND ?',
            [$from, $to]
        );
    }

    /** Verträge, die zum Stichtag liefen (inkl. später gekündigter/beendeter). */
    public static function contractsActiveAt(string $date): array
    {
        return Database::all(
            "SELECT * FROM contracts WHERE status IN ('aktiv','gekuendigt','beendet') AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)",
            [$date, $date]
        );
    }

    public static function activeCustomersAt(string $date): int
    {
        return count(array_unique(array_column(self::contractsActiveAt($date), 'customer_id')));
    }

    /** Ø Laufzeit (Monate) der zum Stichtag laufenden, befristeten Verträge. */
    public static function avgDurationAt(string $date): ?float
    {
        $sum = 0.0;
        $n = 0;
        foreach (self::contractsActiveAt($date) as $c) {
            $m = months_between($c['start_date'], $c['end_date']);
            if ($m !== null) {
                $sum += $m;
                $n++;
            }
        }
        return $n ? round($sum / $n, 1) : null;
    }

    /** @return array{labels:string[],values:int[]} */
    public static function durationBuckets(string $date): array
    {
        $buckets = ['< 6' => 0, '6–12' => 0, '12–24' => 0, '24–36' => 0, '> 36' => 0];
        foreach (self::contractsActiveAt($date) as $c) {
            $m = months_between($c['start_date'], $c['end_date']);
            if ($m === null) {
                $buckets['> 36']++; // unbefristet
                continue;
            }
            $m = round($m);
            $key = match (true) {
                $m < 6 => '< 6',
                $m <= 12 => '6–12',
                $m <= 24 => '12–24',
                $m <= 36 => '24–36',
                default => '> 36',
            };
            $buckets[$key]++;
        }
        return ['labels' => array_keys($buckets), 'values' => array_values($buckets)];
    }

    /** Verteilung laufend / Verlängerung fällig / gekündigt / auslaufend (Stand heute). */
    public static function statusDistribution(): array
    {
        $counts = array_fill_keys(array_keys(ContractService::STATES), 0);
        foreach (Database::all("SELECT * FROM contracts WHERE status IN ('aktiv','gekuendigt')") as $c) {
            $s = ContractService::classify($c);
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        return $counts;
    }

    /** Prognose für einen Monat aus den laufenden Verträgen. */
    public static function forecast(string $ym): array
    {
        $recurring = 0;
        $oneOff = 0;
        $n = 0;
        foreach (Database::all("SELECT * FROM contracts WHERE status IN ('aktiv','gekuendigt')") as $c) {
            $v = ContractService::revenueInMonth($c, $ym);
            if ($v > 0) {
                $n++;
                $c['billing_interval'] === 'einmalig' ? $oneOff += $v : $recurring += $v;
            }
        }
        return ['total' => $recurring + $oneOff, 'recurring' => $recurring, 'one_off' => $oneOff, 'contracts' => $n];
    }

    /** Monatlich wiederkehrender Vertragsumsatz (MRR) je Monat. */
    public static function contractBasisByMonth(string $fromYm, string $toYm): array
    {
        $contracts = Database::all("SELECT * FROM contracts WHERE status IN ('aktiv','gekuendigt','beendet') AND billing_interval <> 'einmalig'");
        $out = [];
        for ($ym = $fromYm; $ym <= $toYm; $ym = Period::shift($ym, 1)) {
            $sum = 0;
            $start = $ym . '-01';
            $end = Period::lastDay($ym);
            foreach ($contracts as $c) {
                if ($c['start_date'] <= $end && (empty($c['end_date']) || $c['end_date'] >= $start)) {
                    $sum += (int) $c['monthly_value_cents'];
                }
            }
            $out[$ym] = $sum;
        }
        return $out;
    }

    /** Offene Angebote aus easybill (nicht angenommen/abgelehnt, nicht zur Rechnung geworden). */
    public static function openOpportunities(): array
    {
        $where = "d.type = 'angebot' AND d.status = 'offen' AND d.is_draft = 0
                  AND NOT EXISTS (SELECT 1 FROM easybill_documents r WHERE r.ref_easybill_id = d.easybill_id AND r.type = 'rechnung')";
        $sum = Database::one("SELECT COALESCE(SUM(d.amount_net_cents),0) AS total, COUNT(*) AS n FROM easybill_documents d WHERE $where");
        $top = Database::all("SELECT d.*, COALESCE(c.name, d.customer_name) AS display_name FROM easybill_documents d LEFT JOIN customers c ON c.id = d.customer_id WHERE $where ORDER BY d.amount_net_cents DESC LIMIT 3");
        return ['total' => (int) $sum['total'], 'count' => (int) $sum['n'], 'top' => $top];
    }

    public static function inbox(int $limit = 6): array
    {
        return Database::all(
            "SELECT * FROM easybill_documents WHERE inbox_state = 'neu' OR suggestion IS NOT NULL
             ORDER BY suggestion IS NULL, imported_at DESC, id DESC LIMIT " . max(1, $limit)
        );
    }
}
