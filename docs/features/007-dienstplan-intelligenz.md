# Dienstplan-Intelligenz

## Status

In Progress

## Ziel

Die Dienstplanung soll über manuelles Eintragen hinausgehen und aktiv helfen:
passende Mitarbeitende vorschlagen, Konflikte anzeigen, Unterbesetzung erkennen
und Plan/Ist-Abweichungen auswerten.

## Warum

Dienstplanung ist bei Wettbewerbern ein starkes Verkaufsargument. WorkDiary hat
bereits Schichttypen, geplante Schichten, Qualifikationen, Abwesenheiten und
Compliance-Regeln. Daraus kann eine praxisnahe Planungshilfe entstehen, ohne
direkt eine komplexe Enterprise-Optimierung bauen zu müssen.

## MVP

- Verfügbarkeiten und Wunschdienste.
- Schichttausch mit Freigabe.
- Vorschlagsliste geeigneter Mitarbeitender nach Qualifikation, Abwesenheit,
  Ruhezeit und Wochenstunden.
- Unter- und Überbesetzungswarnungen.
- Plan/Ist-Auswertung pro Team, Standort oder Zeitraum.

## Akzeptanzkriterien

- Planende sehen vor Veröffentlichung relevante Konflikte.
- Vorschläge erklären, warum ein Mitarbeitender geeignet oder ungeeignet ist.
- Schichttausch verändert den Plan erst nach Freigabe.
- Plan/Ist-Abweichungen sind auswertbar.

## Abhängigkeiten

- `ScheduledShift`
- `ShiftType`
- `CoverageRequirement`
- `Qualification`
- `Vacation`
- `SickLeave`
- `ShiftComplianceService`
- `Attendance`

## GitHub Issues

- TBD
