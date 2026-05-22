# Drilldown von Kennzahl zu Aufträgen

Status: Aktiv (MVP-042, Issue #41) • Quelle:
[Feature 002 — Auswertungen](features/002-auswertungen-entscheidungsgrundlagen.md).
• Aufbauend auf: [Kundenanalyse](kundenanalyse.md) (MVP-039),
[Auftragstypanalyse](auftragstypanalyse.md) (MVP-040),
[Produktanalyse](produkt-analyse.md) (MVP-041).

## 1. Zweck

Jede Kennzahl in jedem Report muss sich per Klick zur **Liste der
zugrunde liegenden Aufträge** (oder Zeitbuchungen) auflösen lassen.
Ohne diesen Drilldown verlieren Reports an Vertrauen.

## 2. Konzept: `DrilldownDescriptor`

Jedes Report-Kennzahl-Element trägt einen Descriptor (vom Builder
geliefert):

```php
final class DrilldownDescriptor {
    public string $target;        // entry|timeEntry|openIssue|protocol
    public array  $filter;        // serialisierbar
    public string $label;         // "Aufträge mit rework_reason gesetzt"
    public ?int   $expectedCount; // für UI-Vorabanzeige
}
```

Beispiele:

| Kennzahl                         | Descriptor                                          |
| -------------------------------- | --------------------------------------------------- |
| `Kundenanalyse.reworkEntryCount` | target=entry, filter={customer:X, rework_reason__notnull, period} |
| `Auftragstyp.overrunCount`       | target=entry, filter={entry_type:X, overrun:true, period}         |
| `Produkt.openIssueCount`         | target=openIssue, filter={subject_type:asset, subject_id:X, status:open} |
| `Kundenanalyse.totalMinutes`     | target=timeEntry, filter={customer:X, period}        |

## 3. Routen

- `/drilldown/entries?{filter}` — universelle Auftragsliste.
- `/drilldown/time-entries?{filter}`
- `/drilldown/open-issues?{filter}`
- `/drilldown/protocols?{filter}`

Filter werden als signed query string übergeben (`filter`-Schlüssel
HMAC, Server validiert), damit niemand per URL-Manipulation breitere
Datenbereiche sieht.

## 4. UI

- Jede Kennzahl-Zelle/Karte ist klickbar mit `cursor: zoom-in`.
- Drilldown-Seite zeigt: Filter-Chips oben (mit „Entfernen"),
  Tabelle der Treffer, Aggregat (Σ Minuten, Σ Aufträge) konsistent
  mit Report-Wert.
- Konsistenz-Check: Wenn Σ in Drilldown ≠ Report-Wert (z. B. wegen
  zwischenzeitlicher Mutation), Hinweis „Daten haben sich seit
  Reportstand geändert" mit Timestamp.

## 5. Permissions

`drilldown.view` — automatisch geprüft anhand der Ziel-Entity-Policy.
Wenn User Auftrag X nicht sehen darf, wird er in der Drilldown-Liste
ausgeblendet (Filter wird zu seiner Sicht beschnitten); Hinweis
„N Datensätze wegen Berechtigungen ausgeblendet".

## 6. Akzeptanzkriterien

1. `DrilldownDescriptor` von allen Report-Buildern erzeugt.
2. Signed Filter-Token implementiert; manipulierte Filter → HTTP
   400.
3. Konsistenz-Check funktioniert; Hinweis angezeigt, wenn Wert
   abweicht.
4. Berechtigungs-Filter mit Hinweis bei ausgeblendeten Treffern.
5. Export aus Drilldown-Seite via MVP-043.

## 7. Out-of-scope

- Bookmark-bare Drilldown-Filter (folgt mit Dashboard-Personalisierung).
- Cross-Report-Drilldown (mehrere Reports gleichzeitig).

## 8. Folge

- MVP-043 Export der Drilldown-Listen und Report-Tabellen.
