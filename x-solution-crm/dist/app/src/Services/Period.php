<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Zeitraumfilter der Topbar: gewählter Bezugsmonat (Y-m), in der Session gemerkt.
 */
final class Period
{
    public static function current(): string
    {
        $req = $_GET['periode'] ?? null;
        if (is_string($req) && self::valid($req)) {
            $_SESSION['periode'] = $req;
        }
        $p = $_SESSION['periode'] ?? null;
        return is_string($p) && self::valid($p) ? $p : date('Y-m');
    }

    public static function valid(string $ym): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym) && $ym <= date('Y-m') && $ym >= date('Y-m', strtotime('-36 months'));
    }

    /** @return array<string,string> Y-m => Label */
    public static function options(int $months = 24): array
    {
        $out = [];
        $d = new \DateTimeImmutable('first day of this month');
        for ($i = 0; $i < $months; $i++) {
            $ym = $d->format('Y-m');
            $out[$ym] = $i === 0 ? 'Aktueller Monat (' . month_label($ym) . ')' : ($i === 1 ? 'Vormonat (' . month_label($ym) . ')' : month_label($ym));
            $d = $d->modify('-1 month');
        }
        return $out;
    }

    public static function shift(string $ym, int $months): string
    {
        return (new \DateTimeImmutable($ym . '-01'))->modify(($months >= 0 ? '+' : '') . $months . ' months')->format('Y-m');
    }

    public static function lastDay(string $ym): string
    {
        return date('Y-m-t', strtotime($ym . '-01'));
    }

    /** Stichtag für Bestandskennzahlen: heute im aktuellen Monat, sonst Monatsende. */
    public static function referenceDate(string $ym): string
    {
        return $ym === date('Y-m') ? date('Y-m-d') : self::lastDay($ym);
    }
}
