# Compliance, Korrekturen und Audit

## Status

In Progress — Zeitkorrektur-Anträge (MVP-017):
[docs/zeit-korrekturen.md](../zeit-korrekturen.md). Monatsfreigabe
(MVP-016): [docs/monatsfreigabe.md](../monatsfreigabe.md).

## Ziel

WorkDiary soll Arbeitszeit-, Dienstplan- und Abrechnungsdaten so nachvollziehbar
machen, dass interne Prüfung, Steuerberatung, Datenschutz und arbeitsrechtliche
Anforderungen unterstützt werden.

## Warum

Compliance ist ein Kaufargument, wenn sie nicht nur als Warntext existiert,
sondern tägliche Prozesse absichert: Korrekturen, Freigaben, Regelverletzungen,
Audit-Trail, Datenexport und Aufbewahrung.

## MVP

- Korrekturanträge für Arbeitszeiten mit Begründung.
- Genehmigungsworkflow für Monatszeiten.
- Audit-Trail für kritische Änderungen an Zeit, Schicht, Urlaub, Krankheit,
  Spesen und Rechnung.
- Compliance-Dashboard mit Regelverletzungen und offenen Prüfungen.
- Exportpaket für geprüfte Zeiträume.

## Akzeptanzkriterien

- Kritische Änderungen sind mit alter und neuer Version nachvollziehbar.
- Abgelehnte Korrekturen bleiben sichtbar.
- Regelverletzungen können erklärt oder behoben werden.
- Prüfexporte sind zeitraum- und organisationsbezogen erzeugbar.

## Abhängigkeiten

- `AuditLog`
- `OrganizationAuditLog`
- `ShiftComplianceService`
- `Attendance`
- `TimeEntry`
- `Expense`
- `Vacation`
- `SickLeave`

## GitHub Issues

- TBD
