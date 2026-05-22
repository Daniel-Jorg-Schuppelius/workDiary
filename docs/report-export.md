# CSV-/PDF-Export für MVP-Reports

Status: Aktiv (MVP-043, Issue #42) • Quelle:
[Feature 002 — Auswertungen](features/002-auswertungen-entscheidungsgrundlagen.md).
• Aufbauend auf: [Kundenanalyse](kundenanalyse.md),
[Auftragstypanalyse](auftragstypanalyse.md),
[Produktanalyse](produkt-analyse.md), [Drilldown](report-drilldown.md).

## 1. Zweck

Jeden MVP-Report sowie jede Drilldown-Liste als **CSV** (für
Weiterverarbeitung) und als **PDF** (für Verteilung an
Geschäftsführung / Kundenreview) exportieren.

## 2. Service `ReportExportService`

`export(ReportDescriptor $report, ExportFormat $format, ExportOptions $opts): ExportArtifact`

`ReportDescriptor` kapselt: Report-Code, Filter (signed wie in
MVP-042), Spaltenwahl, Sort.

`ExportFormat`: `csv`, `pdf`, später `xlsx`.

`ExportArtifact`: bytes + Filename + MIME + SHA-256.

## 3. CSV-Format

- UTF-8 mit BOM (Excel-kompatibel).
- Trennzeichen `;` (DE-Default), konfigurierbar pro Org (`,` oder
  `\t`).
- Datumsformat ISO 8601 `YYYY-MM-DD`.
- Zahlen ohne Tausendertrenner, Dezimaltrenner `.`.
- Erste Datenzeile, davor zwei Metadaten-Zeilen mit `#` Präfix
  (Report-Code, Stand, Filter-Hash) — Excel ignoriert diese mit
  `#`-Marker beim Import nicht; daher Org-Option `csv.meta_lines =
false` (Default true) zum Deaktivieren.
- Spalten enthalten Codes der Klassifikationen, **nicht** Labels
  (für stabile Auswertung), zusätzliche Label-Spalte rechts.

## 4. PDF-Format

- A4, Hochformat (Quer bei breiten Tabellen automatisch).
- Header: Org-Logo (siehe `config/branding.php`), Report-Titel,
  Zeitraum, Filter-Kurzbeschreibung, Stand-Timestamp.
- Footer: Seite N / M, Print-Hash (SHA-256 erste 8 Hex des
  Artefakts), „Generiert von WorkDiary".
- Renderer-Adapter (parallel zu
  [Abnahme & Signatur](abnahme-signatur.md) §6): wkhtmltopdf
  primär, Puppeteer als Fallback.
- Diagramm-Rendering server-seitig via Headless-Browser (für
  Sparklines, Box-Plots).

## 5. Async für große Exporte

- Wenn `estimatedRows > 5000` oder `format = pdf`: Export läuft als
  Queue-Job (`ReportExportJob`), Nutzer bekommt
  In-App-Notification + Download-Link nach Fertigstellung.
- Artefakte landen in
  `storage/exports/{org}/{year}/{report}_{hash8}.{ext}` mit
  Retention 30 Tage (per Scheduled Job `ExportArtifactCleanupJob`
  gelöscht).

## 6. UI

- Jeder Report-Header bekommt Buttons `CSV` / `PDF`.
- Bei Async: Toast „Wird im Hintergrund erstellt" + Link „Status".
- Drilldown-Listen ebenfalls exportierbar (gleiches Service-API).

## 7. Permissions

- `report.export.csv`, `report.export.pdf` — Org-Admin /
  Berechtigte des jeweiligen Reports.
- Audit-Event `report.exported` mit Report-Code, Format,
  Filter-Hash, Datei-Hash.

## 8. Datenschutz / Branding

- PDFs zeigen Org-Branding (siehe `config/branding.php`); Werbung
  oder „WorkDiary"-Marke ist konfigurierbar abschaltbar in
  Org-Settings (außer Footer-Generated-by, das bleibt aus
  Lizenzgründen — siehe
  [Lizenzierung](lizenzierung.md), falls relevant).
- Kunden-PII in CSV/PDF: nur Felder, die im jeweiligen Report
  sichtbar sind; kein Export ungesehener Felder.

## 9. Akzeptanzkriterien

1. CSV-Export aller drei Report-Builder + Drilldown.
2. PDF-Export mit Header/Footer/Logo/Print-Hash und korrekt
   gerenderten Diagrammen.
3. Async-Job mit Notification + Retention-Cleanup.
4. Audit-Event `report.exported` vollständig.
5. Tests: signed Filter wird im Export wiederverwendet (Auditspur).

## 10. Out-of-scope

- xlsx-Export — Folge.
- Geplante / wiederkehrende Exporte (per Schedule mailen).
- Personalisierte Spaltenwahl im Export-Dialog (kommt mit
  Dashboard-Personalisierung).

## 11. Folge

Damit ist der Reports-Cluster (MVP-039..043) im Konzept
abgeschlossen.
