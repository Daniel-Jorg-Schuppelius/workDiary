# Produkt-/Objektanalyse

Status: Aktiv (MVP-041, Issue #40) • Quellen:
[Feature 002 — Auswertungen](features/002-auswertungen-entscheidungsgrundlagen.md),
[Feature 027 — Produkt-/Objektakte](features/027-produkt-objektakte-lebenszyklus.md).
• Aufbauend auf: [Asset-Stammdaten](asset-stammdaten.md) (MVP-035),
[Asset-Verknüpfungen](asset-verknuepfungen.md) (MVP-036).

## 1. Zweck

Wiederkehrende Fehlerarten, offene Punkte und Aufwandsmuster pro
**Asset / Produktgruppe / Hersteller-Modell** sichtbar machen.

## 2. Read-Model `AssetAnalysisBuilder`

Aggregator über `assets`, `asset_diary_entry`, `diary_entries`,
`open_issues`, Klassifikationen `defect_type`, `root_cause`,
`product_group`. 5min Cache.

## 3. Aggregations-Ebenen

### 3.1 Pro Asset

| Kennzahl                | Definition                                          |
| ----------------------- | --------------------------------------------------- |
| `subjectEntryCount`     | Aufträge mit Verknüpfung `role = subject`           |
| `topDefectType`         | Häufigste `defect_type`-Klassifikation              |
| `openIssueCount`        | Offene `open_issues` mit Subject = Asset            |
| `mtbiDays`              | Mean-Time-Between-Incidents in Tagen (zwischen Aufträgen mit Defekt) |
| `lastIncidentAt`        | Letzter Subjekt-Auftrag                             |

### 3.2 Pro Produktgruppe (`product_group`)

| Kennzahl                | Definition                                          |
| ----------------------- | --------------------------------------------------- |
| `assetCount`            | Anzahl Assets mit dieser Produktgruppe              |
| `incidentCount`         | Summe der `subjectEntryCount` über alle Assets      |
| `topDefectTypes`        | Top-3 `defect_type`                                 |
| `incidentRate`          | `incidentCount / assetCount` (im Zeitraum)          |

### 3.3 Pro Hersteller/Modell

Analog §3.2, gruppiert nach `(manufacturer, model)`.

## 4. UI `/reports/assets`

- Tabs: „Pro Asset", „Pro Produktgruppe", „Pro Modell".
- Tabelle mit Sort/Filter, Heatmap (Defekt-Typ × Produktgruppe).
- Top-N Karten: „Auffälligste Assets", „Top Defekt-Typen".
- Drilldown auf Asset → [Objekt-Timeline](objekt-timeline.md)
  gefiltert; auf Defekt-Typ → Auftragsliste (MVP-042).

## 5. Permissions

`reports.assets.view` — Org-Admin, Teamleitung, Qualitätsmanagement.

## 6. Akzeptanzkriterien

1. Builder liefert alle Kennzahlen aus §3 korrekt.
2. MTBI-Berechnung mit Tests (≤ 1 Incident → `NULL`).
3. Heatmap-Datenstruktur dichte Matrix (max. 12 × 12).
4. Drilldown korrekt verlinkt.
5. Performance P95 < 1 s bei 5000 Assets / 365 Tage.

## 7. Out-of-scope

- Vorhersage-Modelle (Predictive Maintenance) — Folge.
- Kostenauswertung pro Asset — Folge.

## 8. Folge

- MVP-042 Drilldown.
- MVP-043 Export.
