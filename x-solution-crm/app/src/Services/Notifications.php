<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Inhalte für Glocke und Sidebar-Zähler (pro Request gecacht).
 */
final class Notifications
{
    private static ?array $cache = null;

    public static function inboxCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM easybill_documents WHERE inbox_state = 'neu' OR suggestion IS NOT NULL");
    }

    public static function openTaskCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM appointments WHERE done_at IS NULL AND (assigned_to = ? OR assigned_to IS NULL)', [Auth::id()]);
    }

    /** @return array<int,array{tone:string,title:string,text:string,url:string}> */
    public static function items(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $items = [];

        $tasks = Database::all(
            "SELECT a.*, c.name AS customer_name FROM appointments a LEFT JOIN customers c ON c.id = a.customer_id
             WHERE a.done_at IS NULL AND a.due_at IS NOT NULL AND a.due_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
               AND (a.assigned_to = ? OR a.assigned_to IS NULL)
             ORDER BY a.due_at LIMIT 5",
            [Auth::id()]
        );
        foreach ($tasks as $t) {
            $overdue = $t['due_at'] < date('Y-m-d 00:00:00');
            $items[] = [
                'tone' => $overdue ? 'danger' : 'warn',
                'title' => $t['title'],
                'text' => ($overdue ? 'Überfällig seit ' : 'Heute, ') . ($overdue ? date_de($t['due_at']) : date('H:i', strtotime($t['due_at'])) . ' Uhr') . ($t['customer_name'] ? ' · ' . $t['customer_name'] : ''),
                'url' => url('/aufgaben'),
            ];
        }

        $new = (int) Database::value("SELECT COUNT(*) FROM easybill_documents WHERE inbox_state = 'neu'");
        if ($new > 0) {
            $items[] = ['tone' => 'info', 'title' => $new . ' neue' . ($new === 1 ? 'r Beleg' : ' Belege') . ' aus easybill', 'text' => 'Noch keinem Kunden zugeordnet', 'url' => url('/easybill')];
        }
        $sugg = (int) Database::value('SELECT COUNT(*) FROM easybill_documents WHERE suggestion IS NOT NULL');
        if ($sugg > 0) {
            $items[] = ['tone' => 'info', 'title' => $sugg . ' Umstellungsvorschl' . ($sugg === 1 ? 'ag' : 'äge'), 'text' => 'Angebot wurde in easybill zur Rechnung', 'url' => url('/easybill')];
        }

        foreach (Reminders::upcoming(30) as $r) {
            if (count($items) >= 9) {
                break;
            }
            $items[] = ['tone' => $r['tone'], 'title' => $r['customer_name'] . ' – ' . $r['title'], 'text' => $r['text'], 'url' => url('/vertraege/' . $r['id'])];
        }
        return self::$cache = $items;
    }
}
