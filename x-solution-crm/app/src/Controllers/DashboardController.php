<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\ContractService;
use App\Services\Period;
use App\Services\Reminders;
use App\Services\Stats;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $p = Period::current();
        $isCurrent = $p === date('Y-m');
        $ref = Period::referenceDate($p);
        $prevYm = Period::shift($p, -1);

        // Monatsumsatz: im laufenden Monat fairer Vergleich bis zum gleichen Tag des Vormonats
        $monthNow = Stats::revenueBetween($p . '-01', $ref);
        $prevEnd = $isCurrent ? min(date('Y-m-d', strtotime($prevYm . '-' . date('d'))), Period::lastDay($prevYm)) : Period::lastDay($prevYm);
        $monthPrev = Stats::revenueBetween($prevYm . '-01', $prevEnd);

        // Jahresumsatz: 1.1. bis Stichtag vs. gleicher Zeitraum im Vorjahr
        $year = (int) substr($p, 0, 4);
        $yearNow = Stats::revenueBetween($year . '-01-01', $ref);
        $refPrevYear = ($year - 1) . substr($ref, 4);
        if (!checkdate((int) substr($refPrevYear, 5, 2), (int) substr($refPrevYear, 8, 2), $year - 1)) {
            $refPrevYear = ($year - 1) . '-02-28';
        }
        $yearPrev = Stats::revenueBetween(($year - 1) . '-01-01', $refPrevYear);

        $custNow = Stats::activeCustomersAt($ref);
        $custPrev = Stats::activeCustomersAt(Period::lastDay($prevYm));
        $durNow = Stats::avgDurationAt($ref);
        $durPrev = Stats::avgDurationAt(Period::lastDay($prevYm));

        $kpis = [
            ['label' => 'Monatsumsatz', 'sub' => month_label($p), 'value' => money($monthNow), 'icon' => 'euro',
             'delta' => pct_change($monthNow, $monthPrev), 'unit' => '%', 'hint' => $isCurrent ? 'ggü. Vormonat (bis ' . date('d.m.', strtotime($prevEnd)) . ')' : 'ggü. ' . month_label($prevYm)],
            ['label' => 'Jahresumsatz', 'sub' => $year . ' bis ' . date_de($ref), 'value' => money($yearNow), 'icon' => 'trend-up',
             'delta' => pct_change($yearNow, $yearPrev), 'unit' => '%', 'hint' => 'ggü. Vorjahreszeitraum'],
            ['label' => 'Aktive Kunden', 'sub' => 'mit laufendem Vertrag', 'value' => number_de($custNow), 'icon' => 'users',
             'delta' => $custNow - $custPrev, 'unit' => '', 'hint' => 'ggü. Ende ' . month_label($prevYm)],
            ['label' => 'Ø Vertragslaufzeit', 'sub' => 'befristete Verträge', 'value' => $durNow !== null ? number_de($durNow, 1) . ' Monate' : '–', 'icon' => 'clock',
             'delta' => $durNow !== null && $durPrev !== null ? round($durNow - $durPrev, 1) : null, 'unit' => ' Mon.', 'hint' => 'ggü. Ende ' . month_label($prevYm)],
        ];

        $fromYm = Period::shift($p, -11);
        $series = Stats::revenueByMonth($fromYm, $p);
        $prevSeries = Stats::revenueByMonth(Period::shift($fromYm, -12), Period::shift($p, -12));
        $revenueChart = [
            'labels' => array_map(fn ($ym) => month_label($ym, true), array_keys($series)),
            'values' => array_map(fn ($c) => round($c / 100, 2), array_values($series)),
            'previous' => array_sum($prevSeries) > 0 ? array_map(fn ($c) => round($c / 100, 2), array_values($prevSeries)) : null,
        ];
        $durationChart = Stats::durationBuckets($ref);

        $dist = Stats::statusDistribution();
        $statusChart = [
            'labels' => array_values(ContractService::STATES),
            'values' => array_values($dist),
            'colors' => ['#3B82F6', '#F59E0B', '#EF4444', '#22D3EE'],
        ];

        $nextYm = Period::shift(date('Y-m'), 1);
        $forecast = Stats::forecast($nextYm);
        $forecastPrev = Stats::forecast(date('Y-m'));
        $opps = Stats::openOpportunities();

        $contracts = ContractService::activeWithCustomer(8);
        $contractCount = (int) Database::value("SELECT COUNT(*) FROM contracts WHERE status IN ('aktiv','gekuendigt')");
        $inbox = Stats::inbox(6);
        $inboxTotal = (int) Database::value("SELECT COUNT(*) FROM easybill_documents WHERE inbox_state = 'neu' OR suggestion IS NOT NULL");
        $reminders = array_slice(Reminders::upcoming(120), 0, 6);

        $this->view('dashboard/index', compact(
            'p', 'kpis', 'revenueChart', 'durationChart', 'statusChart', 'dist', 'forecast', 'forecastPrev', 'nextYm',
            'opps', 'contracts', 'contractCount', 'inbox', 'inboxTotal', 'reminders'
        ) + ['pageTitle' => 'Dashboard', 'useCharts' => true]);
    }
}
