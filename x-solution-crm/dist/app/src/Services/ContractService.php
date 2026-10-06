<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;

final class ContractService
{
    public const STATES = ['laufend' => 'Laufend', 'verlaengerung' => 'Verlängerung fällig', 'gekuendigt' => 'Gekündigt', 'auslaufend' => 'Auslaufend < 60 Tage'];
    public const INTERVAL_MONTHS = ['einmalig' => 0, 'monatlich' => 1, 'quartal' => 3, 'jaehrlich' => 12];

    /**
     * Fachlicher Zustand eines Vertrags für Badges und Donut:
     * laufend | verlaengerung | auslaufend | gekuendigt | entwurf | beendet
     */
    public static function classify(array $c, ?DateTimeImmutable $today = null): string
    {
        $today ??= new DateTimeImmutable('today');
        $status = (string) $c['status'];
        if ($status !== 'aktiv') {
            return $status;
        }
        if (empty($c['end_date'])) {
            return 'laufend';
        }
        $end = new DateTimeImmutable((string) $c['end_date']);
        $daysToEnd = (int) $today->diff($end)->format('%r%a');
        if ($daysToEnd < 0) {
            return 'beendet';
        }
        if ($daysToEnd <= (int) config('contracts.expiring_days', 60)) {
            return 'auslaufend';
        }
        $deadline = $end->modify('-' . (int) $c['notice_days'] . ' days');
        $daysToDeadline = (int) $today->diff($deadline)->format('%r%a');
        if ($daysToDeadline <= (int) config('contracts.renewal_window_days', 45)) {
            return 'verlaengerung';
        }
        return 'laufend';
    }

    public static function stateLabel(string $state): string
    {
        return self::STATES[$state] ?? contract_status_label($state);
    }

    public static function stateBadge(array $c): string
    {
        $state = self::classify($c);
        $label = $state === 'auslaufend' ? 'Auslaufend' : self::stateLabel($state);
        return badge($state, $label);
    }

    /** Letzter Tag, an dem noch fristgerecht gekündigt werden kann. */
    public static function noticeDeadline(array $c): ?string
    {
        if (empty($c['end_date'])) {
            return null;
        }
        return (new DateTimeImmutable((string) $c['end_date']))->modify('-' . (int) $c['notice_days'] . ' days')->format('Y-m-d');
    }

    public static function durationMonths(array $c): ?float
    {
        return months_between($c['start_date'] ?? null, $c['end_date'] ?? null);
    }

    public static function monthlyFromTotal(int $total, string $interval, ?string $start, ?string $end): int
    {
        $months = months_between($start, $end);
        if (!$months || $months < 1) {
            $months = $interval === 'einmalig' ? 1 : 12;
        }
        return (int) round($total / $months);
    }

    /** Jahreswert (Umsatz p. a.) aus dem Monatswert. */
    public static function annualValue(array $c): int
    {
        return (int) $c['monthly_value_cents'] * 12;
    }

    /** Erwarteter Umsatz eines Vertrags in einem Monat (Y-m). */
    public static function revenueInMonth(array $c, string $ym): int
    {
        if (!in_array($c['status'], ['aktiv', 'gekuendigt'], true)) {
            return 0;
        }
        $monthStart = $ym . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        if ($c['start_date'] > $monthEnd || (!empty($c['end_date']) && $c['end_date'] < $monthStart)) {
            return 0;
        }
        if ($c['billing_interval'] === 'einmalig') {
            return substr((string) $c['start_date'], 0, 7) === $ym ? (int) $c['total_value_cents'] : 0;
        }
        return (int) $c['monthly_value_cents'];
    }

    /** Verträge mit Kundenname, optional gefiltert. */
    public static function activeWithCustomer(int $limit = 0): array
    {
        $sql = "SELECT c.*, cu.name AS customer_name FROM contracts c JOIN customers cu ON cu.id = c.customer_id
                WHERE c.status IN ('aktiv','gekuendigt') ORDER BY (c.end_date IS NULL), c.end_date ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return Database::all($sql);
    }

    /** Beendet Verträge, deren Enddatum überschritten ist (vom Cronjob aufgerufen). */
    public static function closeExpired(): int
    {
        return Database::run("UPDATE contracts SET status = 'beendet' WHERE status IN ('aktiv','gekuendigt') AND end_date IS NOT NULL AND end_date < CURDATE()")->rowCount();
    }

    /**
     * Validiert Formulardaten eines Vertrags (auch aus dem easybill-Übernahmedialog).
     *
     * @return array{0: array<string,mixed>, 1: array<string,string>} [Daten, Fehler]
     */
    public static function fromInput(array $in, bool $requireCustomer = true): array
    {
        $errors = [];
        $data = [];
        if ($requireCustomer) {
            $data['customer_id'] = (int) ($in['customer_id'] ?? 0);
            if ($data['customer_id'] <= 0 || !Database::value('SELECT id FROM customers WHERE id = ?', [$data['customer_id']])) {
                $errors['customer_id'] = 'Bitte einen Kunden wählen.';
            }
        }
        $data['title'] = trim((string) ($in['title'] ?? ''));
        if ($data['title'] === '') {
            $errors['title'] = 'Bitte einen Vertragstitel angeben.';
        }
        $data['description'] = trim((string) ($in['description'] ?? '')) ?: null;

        $data['billing_interval'] = (string) ($in['billing_interval'] ?? 'monatlich');
        if (!array_key_exists($data['billing_interval'], self::INTERVAL_MONTHS)) {
            $errors['billing_interval'] = 'Ungültiges Abrechnungsintervall.';
        }
        $data['status'] = (string) ($in['status'] ?? 'aktiv');
        if (!in_array($data['status'], ['entwurf', 'aktiv', 'gekuendigt', 'beendet'], true)) {
            $errors['status'] = 'Ungültiger Status.';
        }

        $data['start_date'] = parse_date_de($in['start_date'] ?? '');
        if ($data['start_date'] === null) {
            $errors['start_date'] = 'Bitte ein gültiges Startdatum (TT.MM.JJJJ) angeben.';
        }
        $endRaw = trim((string) ($in['end_date'] ?? ''));
        $data['end_date'] = $endRaw === '' ? null : parse_date_de($endRaw);
        if ($endRaw !== '' && $data['end_date'] === null) {
            $errors['end_date'] = 'Das Enddatum ist ungültig (TT.MM.JJJJ).';
        } elseif ($data['end_date'] && $data['start_date'] && $data['end_date'] < $data['start_date']) {
            $errors['end_date'] = 'Das Vertragsende liegt vor dem Start.';
        }

        $total = parse_money((string) ($in['total_value'] ?? ''));
        $monthly = parse_money((string) ($in['monthly_value'] ?? ''));
        if ($total === null && $monthly === null) {
            $errors['total_value'] = 'Bitte Gesamtwert oder Monatswert angeben.';
        }
        if (($total ?? 0) < 0 || ($monthly ?? 0) < 0) {
            $errors['total_value'] = 'Beträge dürfen nicht negativ sein.';
        }
        $months = months_between($data['start_date'], $data['end_date']);
        if ($total === null && $monthly !== null) {
            $total = $data['billing_interval'] === 'einmalig' ? $monthly : (int) round($monthly * ($months && $months >= 1 ? $months : 12));
        }
        if ($monthly === null && $total !== null) {
            $monthly = self::monthlyFromTotal($total, $data['billing_interval'], $data['start_date'], $data['end_date']);
        }
        $data['total_value_cents'] = (int) $total;
        $data['monthly_value_cents'] = (int) $monthly;

        $notice = trim((string) ($in['notice_days'] ?? '0'));
        if (!preg_match('/^\d{1,4}$/', $notice)) {
            $errors['notice_days'] = 'Die Kündigungsfrist muss eine Zahl (Tage) sein.';
        }
        $data['notice_days'] = (int) $notice;
        return [$data, $errors];
    }
}
