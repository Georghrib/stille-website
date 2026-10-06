# X-Solution CRM

CRM- und Umsatztool für Kunden, Verträge (Laufzeit, Wert, Kündigungsfrist) und Umsatz mit easybill-Anbindung.
Angebote, Rechnungen und Gutschriften aus easybill werden importiert. Im Posteingang „Aus easybill“ übernimmst du sie wahlweise als **Interessent**, als **Vertrag** oder legst sie nur ab.

- Reines PHP 8.3 + PDO/MySQL, kein Framework, kein Composer, kein npm, kein Build-Schritt
- Vorausgesetzte PHP-Erweiterungen: `curl`, `pdo_mysql`, `mbstring`, `zip`, `openssl`
- Läuft auf Shared Hosting (z. B. Hostinger). Hochladen per FTP oder Dateimanager, kein SSH nötig
- Oberfläche auf Deutsch (Österreich): Beträge im Format `1.234,56 €`, Datum im Format `TT.MM.JJJJ`

---

## Inhalt

1. [Ordnerstruktur auf dem Server](#1-ordnerstruktur-auf-dem-server)
2. [Upload-Paket bauen (dist/)](#2-upload-paket-bauen-dist)
3. [Datenbank im Hosting-Panel anlegen](#3-datenbank-im-hosting-panel-anlegen)
4. [Dateien hochladen (FTP oder Dateimanager)](#4-dateien-hochladen-ftp-oder-dateimanager)
5. [.env einrichten](#5-env-einrichten)
6. [install.php aufrufen](#6-installphp-aufrufen)
7. [Cronjob einrichten](#7-cronjob-einrichten)
8. [easybill verbinden (API-Key und Webhook)](#8-easybill-verbinden-api-key-und-webhook)
9. [Bedienung im Überblick](#9-bedienung-im-überblick)
10. [Updates einspielen](#10-updates-einspielen)
11. [Sicherheit](#11-sicherheit)
12. [Fehlersuche](#12-fehlersuche)
13. [Lokale Entwicklung und Tests ohne easybill-Key](#13-lokale-entwicklung-und-tests-ohne-easybill-key)

---

## 1. Ordnerstruktur auf dem Server

Beim Hoster gibt es pro Domain einen Domain-Ordner (bei Hostinger z. B. `domains/crm.example.at/`). Darin liegt das Web-Verzeichnis `public_html`. Alles Sensible liegt **eine Ebene darüber**, also außerhalb des Web-Verzeichnisses:

```
<domain-ordner>/
├── public_html/            ← nur das, was der Browser sehen darf
│   ├── index.php           (Router, alle Seiten)
│   ├── install.php         (Installation, nach Gebrauch löschen)
│   ├── cron.php            (Abgleich, per Cronjob)
│   ├── easybill-webhook.php
│   ├── .htaccess           (sperrt alles andere)
│   └── assets/             (CSS, JS, Icons)
├── app/                    ← PHP-Klassen und Templates
├── config/                 ← app.php, schema.sql
├── storage/                ← PDFs, Logs, Sperrdateien (nicht öffentlich)
│   ├── pdfs/
│   ├── logs/
│   └── cache/
└── .env                    ← Zugangsdaten (wird bei der Installation angelegt)
```

Alle Pfade im Code werden relativ zu `dirname(__DIR__)` aufgelöst (Konstante `BASE_PATH`). Es ist nichts hart codiert.

## 2. Upload-Paket bauen (dist/)

Das Repository enthält bereits einen fertigen Ordner `dist/`. Er bildet den Domain-Ordner 1:1 ab. Nach Änderungen am Code baust du ihn neu:

```
php build-dist.php        # oder: sh build-dist.sh
```

Dabei werden `public/` → `dist/public_html/`, `app/`, `config/`, ein leeres `storage/` und `.env.example` kopiert. Eine `.env` wird **nie** mitkopiert.

## 3. Datenbank im Hosting-Panel anlegen

1. Im hPanel (Hostinger) unter **Datenbanken → MySQL-Datenbanken** eine neue Datenbank mit Benutzer anlegen.
2. Datenbankname, Benutzername und Passwort notieren (bei Hostinger z. B. `u123456789_crm`).
3. Als Host ist `localhost` richtig.

Tabellen musst du nicht selbst anlegen, das übernimmt `install.php`.

## 4. Dateien hochladen (FTP oder Dateimanager)

**Variante A: Dateimanager (ohne Zusatzprogramm)**

1. `dist/` auf deinem Rechner als ZIP packen: den *Inhalt* von `dist/` markieren → „Komprimieren“.
2. Im hPanel den **Dateimanager** öffnen und in den Domain-Ordner wechseln (die Ebene, in der `public_html` liegt).
3. ZIP hochladen, mit Rechtsklick → **Entpacken** in den Domain-Ordner, danach die ZIP-Datei löschen.
4. Kontrolle: Neben `public_html` liegen jetzt `app/`, `config/`, `storage/` und `.env.example`. In `public_html` liegen `index.php`, `install.php`, `cron.php`, `easybill-webhook.php`, `.htaccess` und `assets/`.

**Variante B: FTP (z. B. FileZilla)**

1. FTP-Zugang im hPanel unter **Dateien → FTP-Konten** nachsehen.
2. Mit FileZilla verbinden und in den Domain-Ordner wechseln.
3. Den Inhalt von `dist/public_html/` in den Server-Ordner `public_html/` ziehen.
4. `dist/app`, `dist/config`, `dist/storage` und `dist/.env.example` in den Domain-Ordner ziehen, also **neben** `public_html`.
5. In FileZilla unter *Server → Versteckte Dateien anzeigen* prüfen, dass `public_html/.htaccess` mit hochgeladen wurde.

**Rechte:** `storage/` und die Unterordner brauchen Schreibrechte für PHP (meist reicht 755, sonst 775). `install.php` prüft das und zeigt Probleme an.

> Liegt `public_html` bei deinem Hoster an anderer Stelle, gilt trotzdem: `app/`, `config/`, `storage/` und `.env` müssen genau **eine Ebene über** dem Web-Verzeichnis liegen.

## 5. .env einrichten

Du hast zwei Möglichkeiten:

**a) Automatisch (empfohlen):** Einfach weiter mit Schritt 6. Fehlt die `.env`, fragt `install.php` die Datenbankdaten und die APP_URL ab, prüft die Verbindung und legt die `.env` selbst im Domain-Ordner an. `WEBHOOK_SECRET` und `CRON_SECRET` werden dabei zufällig erzeugt.

**b) Manuell:** Im Dateimanager `.env.example` kopieren, die Kopie in `.env` umbenennen (im Domain-Ordner, **nicht** in `public_html`) und ausfüllen:

```
DB_HOST=localhost
DB_NAME=u123456789_crm
DB_USER=u123456789_crm
DB_PASS=dein-datenbank-passwort
APP_URL=https://crm.example.at
EASYBILL_API_KEY=
WEBHOOK_SECRET=mindestens-32-zufaellige-zeichen
CRON_SECRET=andere-32-zufaellige-zeichen
```

- `APP_URL` ohne abschließenden Schrägstrich. Mit `https://` wird das Session-Cookie automatisch als `secure` gesetzt.
- `EASYBILL_API_KEY` kann leer bleiben und später unter **Einstellungen** eingetragen werden.
- Zugangsdaten stehen ausschließlich in der `.env`. Sie gehört nie ins Repository (`.gitignore` schließt sie aus).

## 6. install.php aufrufen

1. Im Browser `https://crm.example.at/install.php` öffnen.
2. Die Checkliste zeigt PHP-Version, Erweiterungen und Schreibrechte. Alles muss grün sein.
3. Falls noch keine `.env` existiert: Datenbankdaten eintragen → **Verbindung prüfen & .env anlegen**.
   (Kann PHP die Datei nicht schreiben, zeigt die Seite den fertigen Inhalt zum Kopieren an.)
4. Namen, E-Mail und Passwort (mindestens 10 Zeichen) für den ersten Administrator eintragen.
   Die Checkbox **Demodaten anlegen** füllt das Dashboard mit Beispielkunden, Verträgen und Rechnungen.
5. **Jetzt installieren** legt alle Tabellen an, erzeugt den Admin und sperrt die Installation über `storage/install.lock`. Ein erneuter Aufruf liefert danach HTTP 403.
6. Die Erfolgsseite zeigt die **Cronjob-URL** und die **Webhook-URL** an. Beide notieren.
7. **`public_html/install.php` jetzt im Dateimanager löschen.**
8. Unter `https://crm.example.at/login` anmelden. Weitere Datenbankarbeit ist nicht nötig.

> Demodaten wieder loswerden: Die Tabellen in phpMyAdmin leeren (oder die Datenbank neu anlegen), `storage/install.lock` löschen, `install.php` erneut hochladen und ohne Demodaten installieren.

## 7. Cronjob einrichten

`cron.php` holt alle 15 Minuten nach, was ein Webhook verpasst haben könnte. Es verarbeitet liegengebliebene Webhook-Ereignisse, gleicht geänderte Dokumente ab, lädt fehlende PDFs und setzt abgelaufene Verträge auf „beendet“. Ein Hintergrundprozess ist dafür nicht nötig.

**Variante A: Cronjob im hPanel** (unter **Erweitert → Cron Jobs**), Intervall alle 15 Minuten (`*/15 * * * *`).
Als Befehl entweder PHP direkt (ohne Secret, weil nicht über das Web erreichbar):

```
/usr/bin/php /home/u123456789/domains/crm.example.at/public_html/cron.php
```

oder per URL-Aufruf:

```
wget -q -O /dev/null "https://crm.example.at/cron.php?secret=DEIN_CRON_SECRET"
```

**Variante B: externer Ping-Dienst** (z. B. cron-job.org): die URL `https://crm.example.at/cron.php?secret=DEIN_CRON_SECRET` alle 15 Minuten aufrufen lassen.

Ohne korrektes Secret antwortet `cron.php` mit **HTTP 403**. Das Ergebnis jedes Laufs steht unter **Einstellungen → Synchronisationsprotokoll**. Der Zeitstempel des letzten Abgleichs wird in der Datenbank gespeichert (`app_settings.easybill_last_sync`).

## 8. easybill verbinden (API-Key und Webhook)

1. **API-Key:** In easybill unter *Einstellungen → API* einen API-Schlüssel erzeugen. Im CRM unter **Einstellungen → easybill-Anbindung** eintragen. Der Key wird in die `.env` geschrieben, nicht in die Datenbank.
   Alternativ trägst du ihn direkt in der `.env` bei `EASYBILL_API_KEY=` ein.
2. **Verbindung testen** klicken.
3. **Webhook:** In easybill einen Webhook anlegen. Ziel-URL ist die in den Einstellungen angezeigte Adresse
   `https://crm.example.at/easybill-webhook.php?secret=DEIN_WEBHOOK_SECRET`. In das Pflichtfeld **Secret** denselben Wert wie hinter `?secret=` eintragen, Content-Type `application/json`.
   Ereignisse: `document.create`, `document.update`, `document.completed`, `document.deleted`, `document.payment_add`, `document.payment_delete` sowie unter **Kontakt** `customer.create`, `customer.update`, `customer.delete`. Ansprechpartner- und Positions-Ereignisse werden nicht benötigt.
   Der Empfänger prüft das Secret (sonst HTTP 403), speichert das Ereignis, antwortet sofort mit HTTP 200 und verarbeitet es danach.
4. Unter **Aus easybill → Jetzt abgleichen** kannst du sofort einen ersten Abgleich starten. Beim ersten Lauf werden die Belege der letzten 12 Monate geholt.

**So funktioniert der Import**

| Situation | Ergebnis |
|---|---|
| easybill-ID schon bekannt | Datensatz wird aktualisiert, es entsteht kein Duplikat |
| Kunde hat im CRM bereits einen laufenden Vertrag | Beleg wird still diesem Kunden zugeordnet |
| Kunde ist bekannt (z. B. aus easybill importierter Kontakt), aber ohne Vertrag | Beleg landet im Posteingang **Aus easybill**, der Kunde ist im Dialog vorausgewählt |
| Kein Treffer (easybill-Kunden-ID, ersatzweise E-Mail) | Beleg landet im Posteingang **Aus easybill** |
| Rechnung mit Verweis (`ref_id`) auf ein importiertes Angebot | Es erscheint ein **Umstellungsvorschlag**. Erst nach deiner Bestätigung wird der Kunde aktiviert, der Vertrag angelegt bzw. aktiviert und beide Belege verknüpft |
| Belegtyp Storno, Lieferschein usw. | wird ignoriert (importiert werden Angebote, Rechnungen, Gutschriften) |

**Kontakte:** easybill-Kunden werden als CRM-Kunden übernommen (neu mit Status „Interessent“), per Webhook sofort und zusätzlich stündlich per Cron. Stammdaten (Name, Adresse, E-Mail, Telefon, UID) werden aus easybill aktualisiert, der CRM-Status bleibt unverändert. Wird ein Kontakt in easybill gelöscht, bleibt der CRM-Kunde erhalten und bekommt eine Notiz. Abschalten lässt sich der Kontakt-Import mit `import_customers => false` in `config/app.php`.

PDFs werden beim Import einmalig nach `storage/pdfs/` geladen und nur über `/easybill/{id}/pdf` nach Anmeldung ausgeliefert. Um das easybill-Ratelimit zu schonen, stellt jeder Lauf höchstens 9 API-Anfragen und lädt höchstens 5 PDFs (PLUS-Tarif: 10 Anfragen pro Minute). Mit BUSINESS-Tarif (60 pro Minute) kannst du `max_requests_per_run` in `config/app.php` z. B. auf 50 erhöhen.

## 9. Bedienung im Überblick

- **Dashboard:** Monats- und Jahresumsatz, aktive Kunden und Ø Vertragslaufzeit, jeweils mit Veränderung zum Vorzeitraum. Dazu Umsatzentwicklung über 12 Monate, Vertragslaufzeiten, aktive Verträge, Vertragsstatus, Umsatzprognose für den Folgemonat, offene Opportunities, der easybill-Posteingang sowie Erinnerungen und Verlängerungen. Der **Zeitraumfilter** oben wählt den Bezugsmonat.
- **Kunden:** Suche, Status (Interessent/Kunde/Inaktiv), Kundenlogo (Upload auf der Detailseite, gespeichert in `storage/customer-logos/`) und Detailseite mit Verträgen, Belegen, Notizen und Terminen.
- **Verträge:** Liste mit Filtern, Detail, Anlegen und Bearbeiten, Kündigung erfassen. Der Monatswert wird aus Gesamtwert und Laufzeit vorgeschlagen.
- **Aus easybill:** Posteingang mit Übernahmedialog (Interessent, Vertrag, Nur ablegen).
- **Aufgaben:** Aufgaben und Termine mit Fälligkeit, Zuständigkeit und Kundenbezug.
- **Umsatz:** Auswertung pro Jahr mit CSV-Export (Belege oder Monatssummen, Excel-kompatibel).
- **Einstellungen:** eigenes Passwort, easybill-Zugang, **Erscheinungsbild** (eigenes Logo als PNG/JPG/WebP/SVG und Name, ohne neuen Upload per FTP), Benutzerverwaltung, Synchronisationsprotokoll.

Umsatz bedeutet hier: Rechnungen netto abzüglich Gutschriften, ohne Entwürfe und stornierte Belege. Prognose und Vertragsbasis (MRR) kommen aus den aktiven Verträgen.

## 10. Updates einspielen

1. Lokal `php build-dist.php` ausführen.
2. Die Ordner `dist/app/`, `dist/config/` und den Inhalt von `dist/public_html/` hochladen und dabei überschreiben.
3. **Nicht** überschreiben bzw. löschen: `.env` und den Inhalt von `storage/`.
4. `public_html/install.php` danach wieder löschen. Sie bleibt durch `storage/install.lock` ohnehin gesperrt.

## 11. Sicherheit

- Datenbankzugriff nur über PDO mit Prepared Statements
- CSRF-Token in jedem Formular. POST ohne gültiges Token wird mit 403 abgelehnt
- Passwörter nur mit `password_hash` / `password_verify`. Login-Sperre nach 5 Fehlversuchen pro IP für 5 Minuten
- Alle Seiten außer Login nur nach Anmeldung. Einstellungen zu easybill und Benutzern nur für Administratoren
- Ausgaben werden mit `htmlspecialchars` escaped, dazu gibt es eine Content-Security-Policy
- Session-Cookie mit `httponly`, `SameSite=Lax` und `secure` (bei HTTPS)
- `public_html/.htaccess` erlaubt nur `index.php`, `install.php`, `cron.php`, `easybill-webhook.php` und `assets/`. Alles andere (z. B. `.env`, `.sql`, fremde `.php`-Dateien) liefert 403. `app/`, `config/` und `storage/` haben zusätzlich eigene Sperr-`.htaccess`
- Webhook und Cron sind über lange Secrets geschützt, die Prüfung erfolgt per `hash_equals`
- PDFs liegen außerhalb von `public_html` und werden nur nach Login ausgeliefert
- CSV-Export ist gegen Formel-Injection in Excel geschützt

Empfehlung: Im hPanel SSL aktivieren und „HTTPS erzwingen“ einschalten.

## 12. Fehlersuche

| Problem | Lösung |
|---|---|
| Seite zeigt „Noch nicht installiert“ | `install.php` aufrufen. Falls sie gelöscht wurde, aber `storage/install.lock` fehlt: Lock-Datei prüfen |
| HTTP 500 | `storage/logs/app-JJJJ-MM.log` bzw. `php-error.log` ansehen. Vorübergehend `APP_DEBUG=true` in die `.env` |
| Schöne URLs wie `/kunden` liefern 404 | `public_html/.htaccess` fehlt (versteckte Datei beim FTP-Upload übersehen) |
| „Sitzung abgelaufen“ beim Absenden | Seite neu laden. Cookies für die Domain erlauben |
| Webhook kommt nicht an | Secret in der URL prüfen, im Synchronisationsprotokoll nachsehen. Der Cron holt verpasste Belege trotzdem nach |
| easybill meldet 401/403 | API-Key in Einstellungen bzw. `.env` prüfen |
| Ratelimit (HTTP 429) | Kein Problem: Der nächste Cron-Lauf macht weiter |

## 13. Lokale Entwicklung und Tests ohne easybill-Key

Voraussetzungen: PHP 8.3 mit den oben genannten Erweiterungen und MySQL/MariaDB.

```
# Datenbank anlegen
mysql -e "CREATE DATABASE xcrm CHARACTER SET utf8mb4; CREATE USER 'xcrm'@'localhost' IDENTIFIED BY 'xcrm'; GRANT ALL ON xcrm.* TO 'xcrm'@'localhost';"

# App starten (public/ ist das Web-Verzeichnis)
php -S 127.0.0.1:8080 -t public
# → http://127.0.0.1:8080/install.php

# easybill-Mock starten (bildet die REST-API v1 nach)
php -S 127.0.0.1:8090 tests/mock-easybill/router.php
```

Für den Mock in der lokalen `.env`:

```
EASYBILL_API_KEY=test-key
EASYBILL_BASE_URL=http://127.0.0.1:8090/rest/v1
```

Beispiel-Webhooks liegen in `tests/payloads/`:

```
curl -X POST -H "Content-Type: application/json" \
     --data-binary @tests/payloads/01-document-create-offer.json \
     "http://127.0.0.1:8080/easybill-webhook.php?secret=DEIN_WEBHOOK_SECRET"
```

| Datei | Inhalt |
|---|---|
| `01-document-create-offer.json` | Neues Angebot eines unbekannten Kunden → Posteingang |
| `02-document-update-id-only.json` | Ereignis nur mit ID → Dokument wird per API nachgeladen, Kunde automatisch zugeordnet |
| `03-document-create-invoice-from-offer.json` | Rechnung aus Angebot (`ref_id`) → Umstellungsvorschlag |
| `04-document-create-credit.json` | Gutschrift |
| `05-document-create-storno-ignored.json` | Storno → wird ignoriert |
| `06-document-deleted.json` | Dokument in easybill gelöscht |
| `07-document-payment-add.json` | Zahlung erfasst (enthält nur `document_id`) → Dokument wird nachgeladen |
| `08-customer-create.json` | Neuer easybill-Kontakt → CRM-Kunde (Interessent) |
| `09-customer-update-id-only.json` | Kontakt-Änderung nur mit ID → wird per API nachgeladen |
| `10-customer-delete.json` | Kontakt in easybill gelöscht → Notiz beim CRM-Kunden |

**Abnahmetest** (Login, Dashboard und Diagramme, Webhook, Übernahme als Vertrag, Schutzmechanismen):

```
php tests/acceptance.php http://127.0.0.1:8080 admin@example.at 'DeinPasswort'
```

`tests/` wird nicht in `dist/` übernommen und gehört nicht auf den Server.
