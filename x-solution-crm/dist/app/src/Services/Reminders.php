<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Erinnerungen & Verlängerungen: Verträge, deren Ende oder Kündigungsfrist bald erreicht ist.
 */
final class Reminders
{
    /**
     * @return array<int,array<string,mixed>> sortiert nach Dringlichkeit;
     *   tone: danger (≤ 30 Tage / Frist ≤ 14 Tage), warn (≤ 60 Tage / Frist ≤ 45 Tage), info
     */
    public static function upcoming(int $days = 120): array
    {
        $rows = Database::all(
            "SELECT c.*, cu.name AS customer_name FROM contracts c JOIN customers cu ON cu.id = c.customer_id
             WHERE c.status IN ('aktiv','gekuendigt') AND c.end_date IS NOT NULL
               AND c.end_date >= CURDATE()
               AND (c.end_date <= DATE_ADD(CURDATE(), INTERVAL :d DAY)
                    OR DATE_SUB(c.end_date, INTERVAL c.notice_days DAY) <= DATE_ADD(CURDATE(), INTERVAL :d2 DAY))",
            ['d' => $days, 'd2' => min($days, 60)]
        );
        $out = [];
        foreach ($rows as $c) {
            $daysEnd = days_until($c['end_date']);
            $deadline = ContractService::noticeDeadline($c);
            $daysDeadline = days_until($deadline);
            if ($c['status'] === 'gekuendigt') {
                $tone = $daysEnd <= 30 ? 'danger' : 'warn';
                $text = 'Gekündigt, endet am ' . date_de($c['end_date']) . ' (in ' . $daysEnd . ' Tagen)';
                $sort = $daysEnd;
            } elseif ($daysDeadline !== null && $daysDeadline >= 0 && $daysDeadline <= 60 && $daysDeadline < $daysEnd) {
                $tone = $daysDeadline <= 14 ? 'danger' : 'warn';
                $text = 'Kündigungsfrist endet am ' . date_de($deadline) . ' – Verlängerung klären';
                $sort = $daysDeadline;
            } else {
                $tone = $daysEnd <= 30 ? 'danger' : ($daysEnd <= 60 ? 'warn' : 'info');
                $text = 'Vertragsende am ' . date_de($c['end_date']);
                $sort = $daysEnd;
            }
            $c['tone'] = $tone;
            $c['text'] = $text;
            $c['days'] = $sort;
            $out[] = $c;
        }
        usort($out, fn ($a, $b) => $a['days'] <=> $b['days']);
        return $out;
    }
}
