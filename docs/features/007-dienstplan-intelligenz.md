# Dienstplan-Intelligenz

## Status

MVP umgesetzt (Verfügbarkeiten/Wunschdienste, Schichttausch mit Freigabe,
Besetzungsvorschläge, Unter-/Überbesetzungswarnung). Offen: automatische
Voll-Optimierung (bewusst OUT), erweiterte Plan/Ist-Auswertung.

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

## Umsetzungshinweise (MVP)

- **Verfügbarkeiten/Wunschdienste**: `availability_windows` (wiederkehrend per
  `weekday` ODER datumsbezogen per `specific_date`, Art available/unavailable/
  preferred, optionaler Gültigkeitszeitraum) und `desired_shifts` (want/avoid je
  Datum + optionalem Schichttyp). Self-Service unter
  `schedule.availability.index` (Permission `availability.manage.own`).
- **Schichttausch**: `shift_exchanges` mit Statusmaschine
  requested → accepted → approved (bzw. rejected/cancelled). Freigabe prüft die
  neue Zuordnung über `ShiftComplianceService` (keine parallele Logik) und
  blockt bei ERROR (Override durch die Leitung möglich). Bei Freigabe wechselt
  `ScheduledShift.user_id` (echter Tausch: beide Schichten). Synchrone
  NotificationEvents `shiftExchange.requested`/`.decided`; Scanner-Reminder für
  offene Freigaben.
- **Besetzungsvorschläge**: `StaffingSuggester` rankt Kandidaten nach
  Qualifikation (`ShiftType.qualifications`), Verfügbarkeit/Wunsch und
  Compliance (transiente Proxy-Schicht durch `ShiftComplianceService`;
  ERROR = ausgeschlossen, WARNING = Hinweis). UI-Hook an der offenen Schicht im
  Schichtplan; Zuweisung läuft über den regulären Speichern-Pfad mit
  Compliance-Re-Check.
- **Unter-/Überbesetzung**: bestehende `OpenSlotService`/`CoverageService`
  wiederverwendet; je Tag ein Warn-Badge bei offenen Soll-Schichten.

## GitHub Issues

- TBD
