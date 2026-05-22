# Kundenanalyse

Status: Aktiv (MVP-039, Issue #51) • Quelle:
[Feature 002 — Auswertungen / Entscheidungsgrundlagen](features/002-auswertungen-entscheidungsgrundlagen.md).

## 1. Zweck

Pro Kunde sichtbar machen: Aufwand, Anzahl Aufträge, Nacharbeit,
offene Punkte und nicht abrechenbare Zeit — im Zeitraum, mit Trend
und Top-N.

## 2. Read-Model `CustomerAnalysisBuilder`

Aggregator über `customers`, `diary_entries`, `time_entries`,
`open_issues` (MVP-024), `material_usages`, mit Joins auf
Klassifikationen (`rework_reason`, `result`). Keine eigene Tabelle —
Materialisierung über View / Query mit 5min Cache je
`(org, filterHash)`.

## 3. Kennzahlen je Kunde

| Kennzahl             | Definition                                                                    |
| -------------------- | ----------------------------------------------------------------------------- |
| `entryCount`         | Anzahl `diary_entries` mit `customer_id = X` im Zeitraum                      |
| `totalMinutes`       | Σ `time_entries.duration` mit Bezug auf den Kunden                            |
| `billableMinutes`    | dito, `billable = true`                                                       |
| `nonBillableMinutes` | `totalMinutes - billableMinutes`                                              |
| `nonBillableShare`   | `nonBillableMinutes / totalMinutes`                                           |
| `reworkEntryCount`   | Entries mit Klassifikation `rework_reason != NULL`                            |
| `openIssueCount`     | Offene `open_issues` zum Stichtag (Subjekt = Customer oder via Entry/Project) |
| `escalationCount`    | Entries mit `result = escalated`                                              |
| `avgEntryMinutes`    | `totalMinutes / entryCount`                                                   |
| `trend30d`           | Δ Aufträge letzte 30 vs. vorherige 30 Tage                                    |

## 4. Filter

- Zeitraum (Pflicht, default „letzte 90 Tage").
- Auftragstyp, Tags, Projekt, zuständiger Mitarbeiter.
- Mindest-Aufwand (Anzeige unterdrücken < N Minuten).

## 5. UI `/reports/customers`

- Tabelle: Kunde, Kennzahlen aus §3, Sort-/Filter-bar.
- Top-Karten: „Top 5 Aufwand", „Top 5 Nacharbeit",
  „Top 5 nicht abrechenbar".
- Sparkline pro Kunde (Aufwand pro Woche).
- Drilldown auf Zeile → Kunden-Detailseite mit verlinkter
  Auftragsliste (siehe MVP-042).

## 6. Performance

- Pre-Aggregation per CTE auf `time_entries` (gruppiert nach
  `customer_id` über Project-/Entry-Pfad).
- Index-Bedarf: `time_entries(start_at, billable)` und
  `diary_entries(customer_id, created_at)` (existieren bereits oder
  durch Migration prüfen).
- P95 Ziel: < 800 ms bei 200 Kunden / 90 Tage.

## 7. Permissions

`reports.customers.view` — Org-Admin, Controlling, Vertrieb.

## 8. Audit

Reports lesen nur — kein Audit. Drilldown-Klicks aktivieren
Standard-Audit der jeweiligen Entity.

## 9. Akzeptanzkriterien

1. Builder liefert alle Kennzahlen §3 korrekt; Tests mit Fixtures
   für nicht-trivialen Fall (mehrere Projekte, gemischte
   Billable-Flags, Rework-Klassifikation).
2. Cache greift, Invalidation bei Mutation eines Time-Entry /
   Diary-Entry des Kunden.
3. UI sortiert/filtert nach allen Spalten.
4. Drilldown verlinkt mit gleichem Zeitraum & Filtern.
5. Export-Hooks (CSV) gemäß MVP-043.

## 10. Out-of-scope (MVP-039)

- Umsatz/Marge (kommt erst mit vollständigen Erlösdaten).
- SLA-bezogene Kennzahlen.
- Vergleich Kunden untereinander mit Benchmarks.

## 11. Folge

- MVP-040 Auftragstypanalyse.
- MVP-041 Produkt-/Objektanalyse.
- MVP-042 Drilldown.
- MVP-043 Export.
