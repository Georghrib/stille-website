<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Beispieldaten, damit das Dashboard nach der Installation sofort gefüllt ist.
 * Demo-Belege tragen easybill-IDs ab 990000000 und raw_payload {"demo":true}.
 */
final class DemoData
{
    private const CUSTOMERS = [
        ['Alpenblick Hotels GmbH', 'Mag. Eva Huber', 'office@alpenblick-hotels.at', '+43 512 44 55 10', 'Maria-Theresien-Straße 12', '6020', 'Innsbruck', 'kunde'],
        ['Donau Logistik KG', 'Thomas Gruber', 'thomas.gruber@donau-logistik.at', '+43 732 77 12 00', 'Hafenstraße 3', '4020', 'Linz', 'kunde'],
        ['Wiener Kaffeerösterei e.U.', 'Sophie Wagner', 'hallo@kaffee-wien.at', '+43 1 523 44 87', 'Neubaugasse 40', '1070', 'Wien', 'kunde'],
        ['Steirische Bau AG', 'DI Martin Pichler', 'm.pichler@steirische-bau.at', '+43 316 81 20 30', 'Annenstraße 55', '8020', 'Graz', 'kunde'],
        ['Salzach Medien OG', 'Lisa Moser', 'lisa@salzach-medien.at', '+43 662 87 34 21', 'Linzer Gasse 18', '5020', 'Salzburg', 'kunde'],
        ['Kärntner Seen Tourismus', 'Andreas Steiner', 'a.steiner@kaernten-seen.at', '+43 463 50 60 70', 'Neuer Platz 1', '9020', 'Klagenfurt', 'kunde'],
        ['Bodensee Technik GmbH', 'Julia Hofer', 'julia.hofer@bodensee-technik.at', '+43 5574 48 21', 'Seestraße 7', '6900', 'Bregenz', 'kunde'],
        ['Vinothek Burgenland', 'Michael Leitner', 'service@vinothek-bgld.at', '+43 2682 61 330', 'Hauptstraße 21', '7000', 'Eisenstadt', 'kunde'],
        ['Praxis Dr. Berger', 'Dr. Katharina Berger', 'ordination@praxis-berger.at', '+43 2742 35 11 0', 'Kremser Gasse 9', '3100', 'St. Pölten', 'kunde'],
        ['Mühlviertler Holzbau', 'Stefan Mayr', 'stefan@holzbau-mayr.at', '+43 7942 72 18', 'Stadtplatz 4', '4240', 'Freistadt', 'kunde'],
        ['Tiroler Bergsport GmbH', 'Anna Brunner', 'anna.brunner@bergsport.tirol', '+43 5356 62 22', 'Vorderstadt 15', '6370', 'Kitzbühel', 'kunde'],
        ['Fitnessstudio Pulse', 'Daniel Schwarz', 'daniel@pulse-fitness.at', '+43 1 789 00 12', 'Favoritenstraße 88', '1100', 'Wien', 'interessent'],
        ['Architekturbüro Lindner', 'Arch. Claudia Lindner', 'office@lindner-arch.at', '+43 316 22 98 70', 'Sporgasse 3', '8010', 'Graz', 'interessent'],
        ['Bäckerei Fuchs', 'Franz Fuchs', 'franz@baeckerei-fuchs.at', '+43 2622 23 456', 'Herzog-Leopold-Straße 2', '2700', 'Wiener Neustadt', 'inaktiv'],
    ];

    // [Kunden-Index, Titel, Intervall, Monatswert €, Start (Monate rel.), Laufzeit Monate, Kündigungsfrist Tage, Status]
    private const CONTRACTS = [
        [0, 'Managed Hosting & Wartung', 'monatlich', 1450, -22, 36, 90, 'aktiv'],
        [0, 'Buchungsplattform Lizenz', 'jaehrlich', 820, -10, 13, 60, 'aktiv'],
        [1, 'Flottenportal Betrieb', 'monatlich', 2380, -30, 36, 90, 'aktiv'],
        [2, 'Webshop Betreuung', 'monatlich', 640, -16, 18, 30, 'aktiv'],
        [3, 'Projektportal & Dokumentenmanagement', 'quartal', 1980, -8, 24, 90, 'aktiv'],
        [4, 'Social-Media-Retainer', 'monatlich', 1200, -5, 12, 30, 'aktiv'],
        [5, 'Gästeapp Lizenz', 'jaehrlich', 950, -12, 15, 60, 'aktiv'],
        [6, 'IT-Support Paket M', 'monatlich', 1750, -26, 27, 60, 'aktiv'],
        [7, 'Online-Vinothek Hosting', 'monatlich', 290, -3, 12, 30, 'aktiv'],
        [8, 'Terminbuchung & Recall-System', 'monatlich', 410, -20, 24, 90, 'gekuendigt'],
        [9, 'ERP-Anbindung Wartung', 'quartal', 860, -14, 48, 90, 'aktiv'],
        [10, 'Website Relaunch', 'einmalig', 0, -1, 4, 0, 'aktiv'],
        [10, 'SEO-Betreuung', 'monatlich', 780, -9, 12, 60, 'aktiv'],
        [3, 'Baustellen-Tracking Pilot', 'monatlich', 520, -18, 12, 30, 'beendet'],
        [13, 'Kassensystem-Support', 'monatlich', 180, -30, 24, 60, 'beendet'],
        [1, 'Lagerverwaltung Erweiterung', 'monatlich', 990, 0, 24, 90, 'entwurf'],
    ];

    public static function seed(int $userId): void
    {
        $today = new DateTimeImmutable('today');
        $customerIds = [];
        foreach (self::CUSTOMERS as $i => $c) {
            $customerIds[$i] = Database::insert('customers', [
                'name' => $c[0], 'contact_person' => $c[1], 'email' => $c[2], 'phone' => $c[3],
                'street' => $c[4], 'zip' => $c[5], 'city' => $c[6], 'country' => 'AT', 'status' => $c[7],
                'easybill_customer_id' => 880000 + $i,
                'created_at' => $today->modify('-' . (36 - $i) . ' months')->format('Y-m-d H:i:s'),
            ]);
        }

        $docId = 990000000;
        $invoiceNo = 2024100;
        foreach (self::CONTRACTS as $k => [$ci, $title, $interval, $monthly, $startRel, $months, $notice, $status]) {
            $start = $today->modify('first day of this month')->modify(($startRel >= 0 ? '+' : '') . $startRel . ' months')->modify('+' . ($k % 9) . ' days');
            $end = $start->modify('+' . $months . ' months')->modify('-1 day');
            $monthlyCents = $monthly * 100;
            $total = $interval === 'einmalig' ? 1_480_000 : $monthlyCents * $months;
            if ($interval === 'einmalig') {
                $monthlyCents = (int) round($total / $months);
            }
            $contractId = Database::insert('contracts', [
                'customer_id' => $customerIds[$ci], 'title' => $title, 'description' => 'Demo-Vertrag',
                'total_value_cents' => $total, 'billing_interval' => $interval, 'monthly_value_cents' => $monthlyCents,
                'start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d'), 'notice_days' => $notice,
                'status' => $status, 'cancelled_at' => $status === 'gekuendigt' ? $today->modify('-20 days')->format('Y-m-d') : null,
                'created_by' => $userId,
            ]);
            if ($status === 'entwurf') {
                continue;
            }
            // Rechnungen gemäß Intervall erzeugen (bis heute bzw. Vertragsende)
            $step = ['monatlich' => 1, 'quartal' => 3, 'jaehrlich' => 12, 'einmalig' => 0][$interval];
            $amount = $interval === 'einmalig' ? $total : $monthlyCents * $step;
            $date = $start;
            $last = min($end, $today);
            while ($date <= $last) {
                $paid = $date < $today->modify('-20 days');
                Database::insert('easybill_documents', self::doc(++$docId, [
                    'easybill_customer_id' => 880000 + $ci, 'customer_id' => $customerIds[$ci], 'contract_id' => $contractId,
                    'type' => 'rechnung', 'number' => 'RE-' . (++$invoiceNo), 'title' => $title . ' – ' . month_label($date->format('Y-m'), true),
                    'customer_name' => self::CUSTOMERS[$ci][0], 'customer_email' => self::CUSTOMERS[$ci][2],
                    'amount_net_cents' => $amount, 'document_date' => $date->format('Y-m-d'),
                    'due_date' => $date->modify('+14 days')->format('Y-m-d'),
                    'paid_at' => $paid ? $date->modify('+' . (5 + $docId % 9) . ' days')->format('Y-m-d') : null,
                    'status' => $paid ? 'bezahlt' : ($date->modify('+14 days') < $today ? 'ueberfaellig' : 'offen'),
                    'inbox_state' => 'zugeordnet',
                ]));
                if ($step === 0) {
                    break;
                }
                $date = $date->modify('+' . $step . ' months');
            }
        }

        // Eine Gutschrift
        Database::insert('easybill_documents', self::doc(++$docId, [
            'easybill_customer_id' => 880002, 'customer_id' => $customerIds[2], 'type' => 'gutschrift', 'number' => 'GS-1007',
            'title' => 'Gutschrift Ausfall Juli', 'customer_name' => self::CUSTOMERS[2][0], 'customer_email' => self::CUSTOMERS[2][2],
            'amount_net_cents' => 32000, 'document_date' => $today->modify('-2 months')->format('Y-m-d'), 'status' => 'erledigt', 'inbox_state' => 'zugeordnet',
        ]));

        // Offene Angebote (Opportunities) bei Interessenten
        $offerPulse = self::doc(++$docId, [
            'easybill_customer_id' => 880011, 'customer_id' => $customerIds[11], 'type' => 'angebot', 'number' => 'AN-3051',
            'title' => 'Mitglieder-App & Check-in-System', 'customer_name' => self::CUSTOMERS[11][0], 'customer_email' => self::CUSTOMERS[11][2],
            'amount_net_cents' => 1_860_000, 'document_date' => $today->modify('-12 days')->format('Y-m-d'), 'status' => 'offen', 'inbox_state' => 'interessent',
        ]);
        Database::insert('easybill_documents', $offerPulse);
        $offerLindnerId = Database::insert('easybill_documents', self::doc(++$docId, [
            'easybill_customer_id' => 880012, 'customer_id' => $customerIds[12], 'type' => 'angebot', 'number' => 'AN-3048',
            'title' => 'Projektplattform Architektur', 'customer_name' => self::CUSTOMERS[12][0], 'customer_email' => self::CUSTOMERS[12][2],
            'amount_net_cents' => 940_000, 'document_date' => $today->modify('-25 days')->format('Y-m-d'), 'status' => 'offen', 'inbox_state' => 'interessent',
        ]));
        $lindnerOfferEbId = $docId;
        Database::insert('easybill_documents', self::doc(++$docId, [
            'easybill_customer_id' => 880001, 'customer_id' => $customerIds[1], 'type' => 'angebot', 'number' => 'AN-3055',
            'title' => 'Lagerverwaltung Erweiterung', 'customer_name' => self::CUSTOMERS[1][0], 'customer_email' => self::CUSTOMERS[1][2],
            'amount_net_cents' => 2_376_000, 'document_date' => $today->modify('-6 days')->format('Y-m-d'), 'status' => 'offen', 'inbox_state' => 'zugeordnet',
        ]));

        // Angebot wurde in easybill zur Rechnung → Umstellungsvorschlag
        Database::insert('easybill_documents', self::doc(++$docId, [
            'easybill_customer_id' => 880012, 'customer_id' => $customerIds[12], 'type' => 'rechnung', 'number' => 'RE-' . (++$invoiceNo),
            'title' => 'Projektplattform Architektur – Anzahlung', 'customer_name' => self::CUSTOMERS[12][0], 'customer_email' => self::CUSTOMERS[12][2],
            'amount_net_cents' => 470_000, 'document_date' => $today->modify('-1 day')->format('Y-m-d'), 'due_date' => $today->modify('+13 days')->format('Y-m-d'),
            'status' => 'offen', 'inbox_state' => 'zugeordnet', 'ref_easybill_id' => $lindnerOfferEbId,
            'suggestion' => 'umstellung', 'suggestion_ref_id' => $offerLindnerId,
        ]));

        // Posteingang: neue, nicht zugeordnete Belege
        $inbox = [
            ['angebot', 'AN-3060', 'Digitalisierung Gästeservice', 'Gasthof Zum Goldenen Hirschen', 'reservierung@goldener-hirsch.at', 1_240_000, -2],
            ['rechnung', 'RE-' . (++$invoiceNo), 'Beratung Datenschutz-Audit', 'Ordination Dr. Weiss', 'praxis@dr-weiss.at', 285_000, -1],
            ['angebot', 'AN-3061', 'Wartungsvertrag Netzwerk 24 Monate', 'Autohaus Kogler GmbH', 'f.kogler@autohaus-kogler.at', 3_120_000, 0],
        ];
        foreach ($inbox as $n => [$type, $number, $title, $name, $email, $net, $rel]) {
            Database::insert('easybill_documents', self::doc(++$docId, [
                'easybill_customer_id' => 880100 + $n, 'type' => $type, 'number' => $number, 'title' => $title,
                'customer_name' => $name, 'customer_email' => $email, 'amount_net_cents' => $net,
                'document_date' => $today->modify($rel . ' days')->format('Y-m-d'),
                'due_date' => $type === 'rechnung' ? $today->modify('+14 days')->format('Y-m-d') : null,
                'status' => 'offen', 'inbox_state' => 'neu',
            ]));
        }

        // Notizen
        $notes = [
            [0, 'Jahresgespräch geführt – Interesse an zusätzlichem Hotelstandort in Seefeld.'],
            [1, 'Ansprechpartner IT wechselt ab November zu Frau Berger-Lang.'],
            [3, 'Pilot erfolgreich abgeschlossen, Folgeauftrag im Angebot AN-3055 besprochen.'],
            [8, 'Kündigung schriftlich eingegangen, Grund: Praxisübergabe. Freundlich verabschieden.'],
            [11, 'Erstgespräch am Telefon, Budget ca. 20.000 €. Entscheidung bis Monatsende.'],
        ];
        foreach ($notes as $i => [$ci, $body]) {
            Database::insert('notes', ['customer_id' => $customerIds[$ci], 'user_id' => $userId, 'body' => $body,
                'created_at' => $today->modify('-' . ($i * 4 + 1) . ' days')->setTime(10 + $i, 15)->format('Y-m-d H:i:s')]);
        }

        // Aufgaben & Termine
        $tasks = [
            ['termin', 'Verlängerungsgespräch Alpenblick', 0, '+3 days 10:00'],
            ['aufgabe', 'Angebot Fitnessstudio Pulse nachfassen', 11, '+1 day 09:00'],
            ['aufgabe', 'Rechnung Donau Logistik prüfen', 1, '-1 day 14:00'],
            ['termin', 'Kick-off Architekturbüro Lindner', 12, '+8 days 13:30'],
            ['aufgabe', 'Kündigungsbestätigung Praxis Dr. Berger senden', 8, 'today 16:00'],
        ];
        foreach ($tasks as [$kind, $title, $ci, $when]) {
            Database::insert('appointments', ['kind' => $kind, 'title' => $title, 'customer_id' => $customerIds[$ci],
                'due_at' => $today->modify($when)->format('Y-m-d H:i:s'), 'assigned_to' => $userId, 'created_by' => $userId]);
        }
    }

    private static function doc(int $ebId, array $data): array
    {
        $data += ['amount_net_cents' => 0, 'status' => 'offen'];
        $data['easybill_id'] = $ebId;
        $data['amount_gross_cents'] = (int) round($data['amount_net_cents'] * 1.2);
        $data['raw_payload'] = json_encode(['demo' => true, 'id' => $ebId]);
        return $data;
    }
}
