# Support und Fehlerdiagnose für Kundeninstallationen

## Status

In Progress — Grundsätze und Auditpunkte verbindlich dokumentiert in
[`docs/security/supportzugriff-grundsaetze.md`](../security/supportzugriff-grundsaetze.md)
(MVP-004, Issue #4).

Konzepte für MVP-044/045 liegen vor:
[Diagnose-Seite](../diagnose-seite.md) (MVP-044, Issue #43) und
[Supportbericht](../supportbericht.md) (MVP-045, Issue #44).

MVP-Kern umgesetzt: **Exportierbarer Supportbericht ohne Kundendaten** und
**Health-Zusammenfassung** sind gebaut. Der `SupportReportBuilder`
(`app/Services/Support/SupportReportBuilder.php`) liefert einen
Whitelist-basierten Bericht mit App-Version + Build-Hash,
PHP-/Laravel-/DB-Version, aktiven Modulen + Plugins (über den
signaturfreien `ReleaseManifestService`-Kern), einem Health-Statusblock aus
`system:health --json` (über `app/Services/Support/SupportHealthSummary.php`,
**ohne** DiagnosticsService zu duplizieren), Plugin-Fehlern der letzten 7 Tage
(nur Plugin-ID/Phase/Anzahl), Queue-/Backup-Kennzahlen, Migrations-Stand,
Datensatz-Counts je Tabelle, Konfigurations-/ENV-Schlüsseln (Secrets
redaktiert) und gefilterten Log-/failed_jobs-Auszügen. Ausgabe als ZIP-Bundle,
**reine JSON-Datei** (`support-report-{date}.json`), Browser-Vorschau sowie über
den Artisan-Befehl **`support:report {--output=}`** für CLI/On-Premise. Datensparsamkeit
ist durch den Whitelist-Ansatz und gezielte Negativtests (kein Kundenname, kein
`APP_KEY`) abgesichert. Offen: Diagnose-Seite-Detailausbau (MVP-044) und die
temporäre Supportfreigabe/Session-/Impersonation-Lifecycle (Abschnitt 5 der
Grundsätze).

## Ziel

WorkDiary soll Support und Fehlerdiagnose für lokale Installationen, Private
Cloud und SaaS erleichtern. Betreiber und Kundenadmins sollen Systemzustand,
Version, Lizenz, Jobs, Queues, Speicher, Mail, Push, Backups, Integrationen und
Fehler schnell prüfen können.

## Warum

Ein verkaufbares Produkt braucht wartbare Installationen. Support darf nicht
darauf angewiesen sein, direkt in Kundendaten oder Server einzusehen. Diagnose
muss datenschutzfreundlich, nachvollziehbar und reproduzierbar sein.

## MVP

- Admin-Diagnoseseite mit Systemstatus.
- Exportierbarer Supportbericht ohne fachliche Kundendaten.
- Checks für Lizenz, Version, Migrationen, Queue, Scheduler, Mail, Storage,
  Backups und Integrationen.
- Protokollierte Supportfreigabe durch Kundenadmin.
- Fehlercodes und verständliche Handlungsempfehlungen.

## Akzeptanzkriterien

- Kundenadmins können häufige Betriebsprobleme erkennen.
- Supportberichte enthalten keine Auftrags-, Kunden- oder Personendaten.
- Supportzugriffe werden protokolliert und zeitlich begrenzt.
- Lokale Installationen können ohne direkten Datenbankzugriff diagnostiziert
  werden.

## Abhängigkeiten

- Datenschutz, Sicherheit und Datenlebenszyklus
- Mandantenfähigkeit und Betriebsmodelle
- Backup, Restore und Disaster Recovery
- Release-, Update- und Plugin-Strategie
- Produktmetriken und Betriebsmetriken

## GitHub Issues

- TBD
