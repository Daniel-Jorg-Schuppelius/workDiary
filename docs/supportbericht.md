# Supportbericht ohne fachliche Kundendaten

Status: Aktiv (MVP-045, Issue #44) • Quellen:
[Feature 041 — Support / Fehlerdiagnose](features/041-support-fehlerdiagnose-kundeninstallationen.md),
[Feature 016 — Datenschutz / DSGVO / Datenlebenszyklus](features/016-datenschutz-dsgvo-datenlebenszyklus.md).
• Aufbauend auf: [Diagnose-Seite](diagnose-seite.md) (MVP-044).

## 1. Zweck

Ein **Supportbericht** ist ein Bundle, das der Betreiber einer
WorkDiary-Installation an den Hersteller/Support schickt, um ein
Problem zu analysieren — **ohne** dass darin Kundendaten (Namen,
Adressen, Auftragsinhalte) enthalten sind.

## 2. Inhalt (sortiert nach Risiko)

### 2.1 Immer enthalten

- DiagnosticsReport (aus MVP-044).
- Installations-Metadaten (App-Version, Edition, Build-Hash, ENV
  ohne Secrets).
- Composer-/NPM-Manifest-Hashes (nicht die Inhalte).
- Migration-Stand (`migrations`-Tabelle: name, batch).
- Konfigurations-Schlüssel (nicht Werte) der `config/*.php` Files.
- Anzahl Datensätze pro Tabelle (`information_schema.tables`).
- Letzte 200 `failed_jobs` (Klasse, Exception-Klasse, Trace,
  **keine** Payloads).
- Letzte 500 Zeilen `storage/logs/laravel.log`, gefiltert (siehe
  §3).
- Audit-Event-Counts pro Event-Code letzte 24 h (keine `changes`-
  JSONs).

### 2.2 Auf Wunsch (Opt-in pro Run)

- Schema-Dump (DDL ohne Daten).
- Anonymisiertes Beispiel-Set: 10 Aufträge mit Faker-überschriebenen
  Texten und Klassifikations-Codes (Strukturdiagnose, keine Inhalte).
- Performance-Snapshot (Slow-Query-Log, falls vorhanden).

### 2.3 Niemals enthalten

- Personenbezogene Daten: Namen, Adressen, E-Mails, Telefonnummern,
  IBAN, IPs.
- Klartext-Auftragsinhalte (Title, Description, Comments,
  Communication-Notes).
- Anhänge / Fotos / PDFs.
- Klassifikations-Labels (nur Codes — Codes sind nicht
  personenbeziehbar).
- ENV-Werte (`APP_KEY`, `DB_PASSWORD`, API-Tokens).
- Lizenz-Schlüssel im Klartext (nur Lizenz-ID + Hash).

## 3. Log-Filter

`SupportReportLogFilter` redaktiert vor Aufnahme:

- Regex IBAN, Mail, IPv4/IPv6, Tel, JWT, SSN-ähnliche Muster → durch
  `<redacted:kind>` ersetzt.
- `User`/`Customer`/`Project`-IDs werden durch Surrogat-IDs
  (`user_3`, `customer_42`) ersetzt — IDs bleiben innerhalb des
  Reports konsistent.
- Whitelist von Log-Channels (`scheduler`, `queue`, `mail`); andere
  Channels nur Counts.

## 4. Erzeugung

`POST /admin/support/report` (Permission
`platform.support.export`):

1. Erzeugt JSON-Bundle gemäß §2.
2. Komprimiert als ZIP (mit optionalem Passwort, das per
   E-Mail-Out-of-Band geteilt wird).
3. Berechnet SHA-256-Hash und protokolliert
   `support.reportGenerated` mit Hash, Größe, Inhalts-Optionen,
   User-ID.
4. Bietet Download an oder lädt direkt zu konfigurierter
   Support-Upload-URL hoch (Opt-in).

## 5. Vorab-Review

Vor dem Download wird eine **Inhalts-Übersicht** angezeigt:
„Bundle enthält: 412 KB. Top-Inhalte: Logs (180 KB), failed_jobs
(60 KB), Diagnostics (12 KB) …". Org-Admin muss explizit
„Generieren" klicken.

## 6. Permissions

| Permission                           | Wer                  |
| ------------------------------------ | -------------------- |
| `platform.support.export`            | Plattform-/Org-Admin |
| `platform.support.exportWithSamples` | Plattform-Admin      |

## 7. Audit

`support.reportGenerated`, `support.reportDownloaded`,
`support.reportUploaded` (mit Ziel-URL gehasht).

## 8. Akzeptanzkriterien

1. Bundle-Inhalt §2 vollständig; Tests für jede „Niemals"-Regel §2.3.
   — erledigt: `app/Services/Support/SupportReportBuilder.php` deckt alle
   Pflicht-Sektionen ab (Installation, DiagnosticsReport, Composer/NPM-Hashes,
   Migrations, Config-Schlüssel, ENV-Schlüssel mit Redaktion sensibler Werte,
   Tabellen-Counts, failed_jobs ohne Payloads, gefilterter Log-Tail,
   Audit-Event-Counts der letzten 24 h). Tests in
   `tests/Feature/Support/SupportReportBuilderTest.php` prüfen die
   Niemals-Regeln: keine Kundendaten (`name`), keine Admin-E-Mail, kein
   `LICENSE_KEY`-Wert.
2. `SupportReportLogFilter` redaktiert mit Tests (mind. 10 Beispieltexte).
   — erledigt: `app/Services/Support/SupportReportLogFilter.php` mit
   IBAN/E-Mail/IPv4/IPv6/Telefon/JWT-Mustern und konsistenten ID-Surrogaten;
   `tests/Feature/Support/SupportReportLogFilterTest.php` mit 10 Tests.
3. ZIP-Erzeugung mit / ohne Passwort, Hash korrekt. — erledigt:
   `app/Services/Support/SupportReportPackager.php` (AES-256, wenn vom System
   unterstützt) + Tests in `SupportReportPackagerTest.php`. SHA-256 wird über
   `hash_file('sha256', $path)` bestätigt.
4. Audit-Events §7. — erledigt: `support.reportGenerated` und
   `support.reportDownloaded` werden im
   `Admin\SupportReportController::generate` geschrieben.
   `support.reportUploaded` ist out-of-scope für diesen Iterationsschritt
   (gehört zur Upload-URL-Option in §4 Schritt 4, ebenfalls out-of-scope).
5. Inhalts-Übersicht zeigt Größen und Optionen. — erledigt:
   `SupportReportPackager::preview()` liefert Top-Sektionen nach Größe,
   `resources/views/admin/support/report.blade.php` rendert das Vorab-Review
   inkl. Opt-In-Checkboxen und Passwortfeld.

## 9. Out-of-scope (MVP-045)

- Automatischer Versand (Mail).
- Verschlüsselung mit PGP-Schlüssel des Supports.
- Continuous-Telemetry („Phone Home").

## 10. Folge

- MVP-046 Backup/Restore-Doku.
- MVP-047 Lizenzstatus.
