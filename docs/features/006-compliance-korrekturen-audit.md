# Compliance, Korrekturen und Audit

## Status

In Progress — Zeitkorrektur-Anträge (MVP-017):
[docs/zeit-korrekturen.md](../zeit-korrekturen.md). Monatsfreigabe
(MVP-016): [docs/monatsfreigabe.md](../monatsfreigabe.md).
**ArbZG-Compliance-Auswertung auf der Ist-Arbeitszeit** umgesetzt
(`reports.arbzg-compliance`): `AttendanceComplianceChecker` prüft die
tatsächlich erfasste Arbeitszeit (Attendance, netto nach Pausen) je
Mitarbeiter/Tag gegen die ArbZG-Schwellen aus dem Bestand
(Organisations-Compliance-Einstellungen wie bei der Dienstplan-Prüfung,
Pausenregeln wie im `DayClosureValidator`) — Tageshöchstarbeitszeit,
Ruhezeit, Pflichtpause und (als Hinweis) Wochenhöchstarbeitszeit. Report mit
Filter, Summen, Drill-down zum Tagesabschluss, CSV/PDF und Verweis auf
genehmigte Zeitkorrekturen; Permission `compliance.viewAny`. Verstöße werden
on-the-fly berechnet (keine eigene `compliance_findings`-Persistenz, da die
Anwesenheiten über die Audit-Hash-Kette ohnehin revisionssicher sind — eine
Persistenz mit Acknowledge-Workflow ist als Folgeschritt notiert). Offen
bleiben: Compliance-Dashboard, Exportpaket für geprüfte Zeiträume.

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
