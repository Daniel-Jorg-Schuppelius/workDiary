# Diagnose-Seite

Status: Aktiv (MVP-044, Issue #43) • Quelle:
[Feature 041 — Support / Fehlerdiagnose Kundeninstallationen](features/041-support-fehlerdiagnose-kundeninstallationen.md).

## 1. Zweck

Eine einzige Admin-Seite, auf der Plattform-Admin (und bei
Selbst-Hosting auch der Betreiber) sofort sieht, ob die Installation
gesund ist: **Version, Lizenz, Queue, Scheduler, Mail, Storage,
Backupstatus**.

## 2. Route und Aufbau

`GET /admin/diagnostics` — geschützt durch Permission
`platform.diagnostics.view`.

Seite ist als Kachel-Grid aufgebaut. Jede Kachel hat:

- Titel + Material-Symbol-Icon
- Status-Pill (`ok` grün, `warn` gelb, `critical` rot, `unknown`
  grau)
- Kennzahlen (z. B. „Queue: 12 wartend, 0 fehlgeschlagen")
- Letzter Check-Timestamp
- Detail-Drawer (rechte Seite öffnet sich)

## 3. Sektionen

### 3.1 Version

- App-Version (`config('app.version')`), Build-Hash, PHP-Version,
  Laravel-Version, DB-Version.
- Verfügbarkeit-Check gegen `update.workdiary.app/version.json` (mit
  Cache 6 h; abschaltbar bei Air-Gap).

### 3.2 Lizenz

- Status (`valid|expiringSoon|expired|missing|invalid`), Ablauf,
  Edition, Nutzeranzahl (Lizenz/Verwendet), aktive Feature-Flags
  (Verweis auf [Lizenzstatus & Feature-Flags](lizenz-admin.md)
  MVP-047).

### 3.3 Queue

- Per Connection: pending, processing, failed, lastFailedAt.
- Worker-Heartbeat (`Cache::get('queue.worker.heartbeat')`).
- Warn-Schwellen: pending > 200, failed > 0, heartbeat älter 5 min.

### 3.4 Scheduler

- Letzter Lauf von `php artisan schedule:run` (Heartbeat in Cache).
- Letzte erfolgreiche Ausführung der wichtigsten Jobs
  (z. B. `AssetOverdueBlockJob`, `ExportArtifactCleanupJob`,
  `BackupJob` siehe §3.7).
- Warn: kein Lauf > 5 min.

### 3.5 Mail

- Konfiguration (Driver, From-Adresse).
- Erfolg/Fehler letzte 24 h (`mail_logs`-Tabelle, sofern vorhanden
  — sonst Failed-Jobs der Queue).
- Test-Button „Test-Mail senden" an angemeldeten Plattform-Admin.

### 3.6 Storage

- Verwendeter Speicher pro Disk (`local`, `public`, `s3`).
- Belegung gegen Quota (sofern Quota gesetzt).
- Anzahl Anhänge, Größe von Exports/PDFs.
- Warn: belegt > 80 %; Critical > 95 %.

### 3.7 Backupstatus

- Letztes erfolgreiches Backup (Zeit, Größe, Ziel, Hash).
- Älteste vorhandene Wiederherstellungsbasis.
- Restore-Test letzte Ausführung (falls automatisiert).
- Warn: kein Backup > 26 h. Critical > 72 h.
- Detail-Link auf [Backup-/Restore-Doku](backup-restore.md)
  (MVP-046).

## 4. Datenmodell

Keine eigene Tabelle: Diagnostics liest Live-Werte aus:

- `cache` (Heartbeats),
- `jobs` / `failed_jobs`,
- `audit_logs` (Backup-Events),
- File-System (Disk-Sizes),
- Application Config + ENV.

`DiagnosticsService::collect(): DiagnosticsReport` führt alle Checks
aus mit Timeout 5 s pro Check (Parallel-Ausführung mit Laravel
`concurrent`).

## 5. JSON-Endpoint für Monitoring

`GET /admin/diagnostics.json` liefert maschinenlesbare
`DiagnosticsReport` (für Prometheus-Adapter, externe Monitoring-
Tools). Auth via `platform.diagnostics.view` ODER signed Token
(`monitoring_tokens`-Tabelle, ähnlich Email-Signatur-Tokens).

## 6. Permissions

- `platform.diagnostics.view` — Plattform-Admin und (bei
  Selbst-Hosting) Org-Admin der „System-Org".
- `platform.diagnostics.runCheck` — Test-Mail/Test-Backup-Trigger.

## 7. Audit

Aufrufe der JSON-API werden geloggt (`diagnostics.viewed`).
Test-Aktionen → `diagnostics.testTriggered` mit Kontext.

## 8. Akzeptanzkriterien

1. UI mit 7 Sektionen wie §3, alle mit Status-Pill und Detail.
2. `DiagnosticsService::collect` mit Timeout-Schutz pro Check,
   keine Block-Risiken.
3. JSON-Endpoint maschinenlesbar.
4. Tests pro Check-Funktion (mit Mock).
5. Performance: Seitenaufruf < 1 s bei normalen Bedingungen.

## 9. Out-of-scope (MVP-044)

- Historische Zeitreihen (Prometheus-Adapter ist nur „später").
- Selbst-Healing (z. B. Queue-Worker neu starten).

## 10. Folge

- MVP-045 Supportbericht baut auf den hier gesammelten Daten auf.
- MVP-046 Backup-/Restore-Doku verweist auf Sektion §3.7.
- MVP-047 Lizenz-/Feature-Flag-Admin ergänzt §3.2.
