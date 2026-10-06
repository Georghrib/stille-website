<?php
/**
 * Allgemeine, nicht geheime Einstellungen.
 * Zugangsdaten (Datenbank, API-Keys, Secrets) gehören ausschließlich in die .env!
 */
return [
    'name' => 'X-Solution CRM',
    'timezone' => 'Europe/Vienna',
    'locale' => 'de_AT',

    'easybill' => [
        // Kann für Tests per EASYBILL_BASE_URL in der .env überschrieben werden (Mock-Server).
        'base_url' => 'https://api.easybill.de/rest/v1',
        'timeout' => 20,
        // Dokumenttypen, die importiert werden (easybill-Typ => CRM-Typ)
        'types' => ['OFFER' => 'angebot', 'INVOICE' => 'rechnung', 'CREDIT' => 'gutschrift'],
        // Rückblick beim Cron-Abgleich über das Belegdatum (Tage vor dem letzten Lauf)
        'lookback_days' => 45,
        // Maximal neu geladene PDFs pro Cron-Lauf (Schutz vor dem easybill-Ratelimit)
        'pdfs_per_run' => 5,
        // Höchstzahl API-Anfragen pro Aufruf (Webhook, Cron, manueller Abgleich).
        // easybill erlaubt im Tarif PLUS 10 Anfragen/Minute, im Tarif BUSINESS 60.
        // Bei BUSINESS kann der Wert z. B. auf 50 erhöht werden.
        'max_requests_per_run' => 9,
    ],

    'contracts' => [
        // Vertrag gilt als "Auslaufend", wenn das Ende innerhalb so vieler Tage liegt
        'expiring_days' => 60,
        // "Verlängerung fällig", wenn der letzte Kündigungstag innerhalb so vieler Tage liegt
        'renewal_window_days' => 45,
    ],
];
