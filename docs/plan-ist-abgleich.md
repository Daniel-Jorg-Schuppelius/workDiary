# Plan/Ist-Abgleich (Anwesenheit, Projektzeit, Schicht)

Status: Aktiv (MVP-018, Issue #18) • Quellen:
[Feature 001 — Zeiterfassung](features/001-zeiterfassung-kernprodukt.md),
[Feature 014 — Nachkalkulation/Wirtschaftlichkeit](features/014-nachkalkulation-wirtschaftlichkeit.md).
• Aufbauend auf:
[Tagesabschluss](tagesabschluss.md),
[Monatsfreigabe](monatsfreigabe.md),
[Zeit-Korrekturen](zeit-korrekturen.md).

## 1. Zweck

Drei Ebenen Plan/Ist gegenüberstellen — nicht vermischen:

1. **Anwesenheit** (Schicht/Arbeitszeitmodell → Stempelung).
2. **Projektzeit** (geplanter Auftrag → erfasste Buchung am Auftrag).
3. **Schichtbesetzung** (geplante Schicht → wer tatsächlich gearbeitet).

Ziel: Abweichungen sichtbar machen, ohne sie still zu glätten. Nutzbar
für Tagesabschluss-Warnungen (MVP-015), Monatsfreigabe (MVP-016),
Nachkalkulation (späterer MVP).

## 2. Drei Ebenen, drei Reports

### 2.1 Anwesenheits-Plan/Ist (pro Tag, pro Mitarbeitendem)

| Größe         | Quelle                                        |
| ------------- | --------------------------------------------- |
| Plan-Start    | `WorkSchedule.expected_start` für Datum.      |
| Plan-Ende     | `WorkSchedule.expected_end`.                  |
| Plan-Stunden  | aus Modell (Soll-Stunden).                    |
| Ist-Start     | erste Stempelung (Attendance).                |
| Ist-Ende      | letzte Stempelung des Tages.                  |
| Ist-Stunden   | Netto-Arbeit (siehe Tagesabschluss §2.5).     |
| Δ Start       | Ist-Start − Plan-Start.                       |
| Δ Stunden     | Ist − Plan.                                   |

Schwellen (organisationsspezifisch konfigurierbar):

- Start-Abweichung > 15 min ⇒ Warnung `presence.lateStart`.
- Stunden-Abweichung > 10 % ⇒ Warnung `presence.hoursDiff`.

### 2.2 Projektzeit-Plan/Ist (pro Auftrag/Projekt)

| Größe                  | Quelle                                                |
| ---------------------- | ----------------------------------------------------- |
| Plan-Stunden Auftrag   | `DiaryEntry.planned_hours` (aus Lebenszyklus §1).     |
| Ist-Stunden Auftrag    | Summe `TimeEntry.duration` am Auftrag.                |
| Δ Stunden              | Ist − Plan.                                           |
| Plan-Stunden Projekt   | Summe Plan über alle Aufträge.                        |
| Ist-Stunden Projekt    | Summe Ist über alle Aufträge.                         |
| Abrechenbar (Ist)      | Summe billable Buchungen.                             |
| Nacharbeit (Ist)       | Buchungen mit Activity-Flag `rework` (MVP-029 ff.).   |

Bei Aufträgen ohne `planned_hours` wird Plan = 0 angezeigt und mit
Status `noPlan` markiert (kein Alarm).

### 2.3 Schicht-Plan/Ist (pro Schicht)

| Größe                 | Quelle                                              |
| --------------------- | --------------------------------------------------- |
| Plan-Besetzung        | `ScheduledShift` Liste der Soll-Personen.           |
| Ist-Besetzung         | tatsächlich an dem Tag/Zeitraum stempelnde Personen.|
| Fehlend               | Plan ohne Ist (Abwesenheit prüfen).                 |
| Zusätzlich            | Ist ohne Plan (Eintragsbedarf).                     |
| Krankheit/Urlaub      | aus `Vacation`/`SickLeave` für Plan-Personen.       |

Aggregat „Auslastung Schicht": `Ist / Plan` in %.

## 3. Datenquellen (Read-Model)

Neuer Service `PlanIstReportBuilder` baut on-demand drei Aggregate:

- `PlanIstReportBuilder::presenceFor(userId, range)`
- `PlanIstReportBuilder::projectTimeFor(scope, range)` — scope = Auftrag/Projekt/Kunde
- `PlanIstReportBuilder::shiftFor(shiftId)` / `forRange(range)`

Keine eigene Persistenz; alle Daten kommen aus bestehenden Tabellen.
Caching pro `(scope, range_hash)` 5 min (Reports werden frequent
abgerufen, ändern sich aber selten innerhalb desselben Tages).

## 4. UI

### 4.1 Persönlicher Plan/Ist (Tag)

Block im Tagesabschluss-Header: „Plan 08:00–16:30 (8 h) — Ist
08:14–16:42 (7 h 58)". Warnung-Pillen, wenn Schwellen verletzt.

### 4.2 Monatsbericht (Mitarbeitend)

Tabelle pro Tag: Plan-Std / Ist-Std / Δ / Warnungen. Klick zeigt Tag.

### 4.3 Auftrags-Plan/Ist

Karte in der [Fallakte](fallakte.md) Seitenspalte (§4.1
Kennzahlen-Karte): „Plan/Ist: 24 / 18.5 h (−5.5 h)". Drill-down zur
Buchungsliste.

### 4.4 Schicht-Board

Kalender-Ansicht (Folge-MVP): pro Schicht Plan-Besetzung + Ist mit
Farb-Indikator (grün = vollständig, gelb = teil, rot = unterbesetzt).

## 5. Warnungen / Audit

Plan/Ist erzeugt **keine** Audit-Events (Read-only). Aber Warnungen
fließen in:

- Tagesabschluss-Sektion D (weiche Warnungen).
- Monatsfreigabe `warnings_count` und `totals.warnings`.

## 6. Permissions

| Bericht                | Wer                                  |
| ---------------------- | ------------------------------------ |
| Eigener Plan/Ist Tag   | jeder Mitarbeitende.                 |
| Team-Plan/Ist          | `report.presence.team`.              |
| Org-Plan/Ist           | `report.presence.organization`.      |
| Auftrags-Plan/Ist      | wer den Auftrag sehen darf.          |
| Schicht-Plan/Ist       | `shift.plan.view`.                   |

## 7. Akzeptanzkriterien

1. Service `PlanIstReportBuilder` liefert die drei Aggregate aus §3.
2. Auftrags-Plan/Ist nutzt `DiaryEntry.planned_hours` aus Lebenszyklus;
   Aufträge ohne Plan markiert als `noPlan`.
3. Persönlicher Plan/Ist im Tagesabschluss-Header zeigt Δ + Pillen.
4. Schicht-Plan/Ist listet fehlende/zusätzliche Personen mit
   Abwesenheits-Begründung.
5. Warnungen-Codes (`presence.lateStart`, `presence.hoursDiff`,
   `shift.understaffed`) sind im Glossar dokumentiert.
6. Performance: Bericht „Monat eines Mitarbeitenden" P95 < 300 ms bei
   einem Jahr historischer Daten.
7. Permissions wie §6 in Policy + Test.

## 8. Out-of-scope (MVP-018)

- Kostenseite (€) — kommt in MVP-Nachkalkulation.
- Forecast für Restmonat.
- KI-Erklärung von Abweichungen.
- Echtzeit-Schichtboard mit WebSocket-Updates.

## 9. Folge-MVPs

- **MVP-040** Auftragstypanalyse (Plan/Ist über Typ).
- **MVP-041** Produkt-/Objektanalyse.
- Folge: Wirtschaftlichkeit (€-Plan/Ist), Schicht-Board (Kalender).
