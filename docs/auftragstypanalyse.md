# Auftragstypanalyse

Status: Aktiv (MVP-040, Issue #39) • Quellen:
[Feature 002 — Auswertungen](features/002-auswertungen-entscheidungsgrundlagen.md),
[Feature 014 — Nachkalkulation / Wirtschaftlichkeit](features/014-nachkalkulation-wirtschaftlichkeit.md).

## 1. Zweck

Pro **Auftragstyp** (Klassifikation `entry_type`, siehe MVP-030)
sichtbar machen: Plan/Ist, Durchschnittsdauer, Nacharbeitsquote,
typische Auffälligkeiten.

## 2. Read-Model `EntryTypeAnalysisBuilder`

Aggregator über `diary_entries`, `time_entries`,
`classifications`, `open_issues`. 5min Cache.

## 3. Kennzahlen je Auftragstyp

| Kennzahl                | Definition                                          |
| ----------------------- | --------------------------------------------------- |
| `entryCount`            | Anzahl Aufträge mit `entry_type = X`                |
| `avgPlannedMinutes`     | Σ `diary_entries.planned_minutes` / count           |
| `avgActualMinutes`      | Σ `time_entries.duration` mit Bezug / count         |
| `planActualRatio`       | `avgActualMinutes / avgPlannedMinutes`              |
| `overrunCount`          | Aufträge mit `actual > planned * 1.2`               |
| `overrunShare`          | `overrunCount / entryCount`                         |
| `reworkCount`           | Aufträge mit `rework_reason != NULL`                |
| `reworkShare`           | `reworkCount / entryCount`                          |
| `firstTimeRightShare`   | `1 - reworkShare - escalationShare`                 |
| `medianActualMinutes`   | Median statt Durchschnitt (robuster)                |
| `p90ActualMinutes`      | 90er Perzentil                                      |

## 4. UI `/reports/entry-types`

- Tabelle pro Auftragstyp mit Kennzahlen aus §3.
- Box-Plot der Bearbeitungsdauer.
- Ampel für Plan/Ist-Ratio (grün ≤ 1.0, gelb ≤ 1.2, rot > 1.2).
- Filter wie MVP-039 (Zeitraum, Kunde, Mitarbeiter, Tag).
- Klick auf Zeile → Drilldown (MVP-042) auf zugrunde liegende
  Aufträge.

## 5. Pflicht-Voraussetzung

`avgPlannedMinutes` setzt voraus, dass `diary_entries` ein Feld
`planned_minutes` hat. Falls nicht vorhanden, in MVP-040 hinzufügen:

```sql
ALTER TABLE diary_entries
  ADD COLUMN planned_minutes INT NULL AFTER status,
  ADD COLUMN planned_at TIMESTAMP NULL,
  ADD COLUMN planned_by_user_id BIGINT NULL;
```

Migration ist Teil des Acceptance Scope dieses MVPs.

## 6. Permissions

`reports.entryTypes.view` — Org-Admin, Teamleitung, Controlling.

## 7. Akzeptanzkriterien

1. Migration `planned_minutes` + Felder; Backfill = NULL ist okay.
2. Builder liefert Kennzahlen §3; Tests inkl. Edge-Cases (entries
   ohne planned_minutes werden in `planActualRatio` ausgelassen).
3. UI mit Filter, Box-Plot, Ampel.
4. Drilldown via MVP-042.

## 8. Out-of-scope

- Kostenbasierte Auswertung (Marge) — kommt mit Erlösdaten.
- Vergleich Org untereinander.

## 9. Folge

- MVP-041 Produkt-/Objektanalyse.
- MVP-042 Drilldown.
