# Objekt-Timeline

Status: Aktiv (MVP-037, Issue #37) • Quellen:
[Feature 027 — Produkt-/Objektakte](features/027-produkt-objektakte-lebenszyklus.md),
[Feature 023 — Suche, Timeline, Fallakte](features/023-suche-timeline-fallakte.md).
• Aufbauend auf: [Asset-Stammdaten](asset-stammdaten.md) (MVP-035),
[Asset-Verknüpfungen](asset-verknuepfungen.md) (MVP-036).

## 1. Zweck

Chronologische, gefilterte Sicht auf alle Ereignisse rund um ein
Asset / Objekt: Aufträge, Protokolle, Statuswechsel, Wartungen,
Materialverbrauch, Anhänge, Eigentümerwechsel, Standortwechsel.

## 2. Read-Model `AssetTimelineBuilder`

Kein eigener Schreib-Speicher. Aggregator über vorhandene Quellen:

| Quelle                 | Event-Typen                                  |
| ---------------------- | -------------------------------------------- |
| `diary_entries`        | erstellt, abgeschlossen, geöffnet, archiviert |
| `protocols`            | erstellt, signiert, archiviert, superseded   |
| `material_usages`      | verbaut, retourniert                          |
| `audit_logs` (asset.*) | created, statusChanged, healthChanged, moved, decommissioned, ownershipTransferred |
| `attachments`          | hinzugefügt (Asset oder verlinktes Objekt)   |
| `procedure_runs`       | gestartet, abgeschlossen, abgebrochen        |

Aggregator liefert generische Items:

```php
final class TimelineItem {
    public string $type;            // diaryEntry|protocol|material|audit|attachment|procedure
    public string $subType;         // created|completed|...
    public CarbonImmutable $occurredAt;
    public ?int $actorUserId;
    public string $title;
    public ?string $summary;
    public array $links;            // ['diaryEntry' => 123, ...]
    public string $iconKey;
    public string $severity;        // info|warning|critical
}
```

## 3. API

`GET /api/assets/{id}/timeline?from=&to=&type=&limit=50&cursor=`

- Default-Window: letzte 365 Tage.
- Filter `type[]` (mehrfach): nur gewünschte Quellen.
- Cursor-basierte Paginierung (`occurredAt` + `id`-Tiebreak,
  desc).
- Cache 60 s (`asset.timeline.v1.{asset}.{filterHash}`),
  Invalidation bei betroffenen Mutationen via Events.

## 4. UI

- Vertikale Timeline auf Asset-Detailseite, Tab „Verlauf".
- Linke Spalte Datum/Zeit, rechte Spalte Karten mit Icon, Titel,
  Summary, Links.
- Filter-Chips oben (Aufträge, Protokolle, Material, Status, …).
- Zeitstrahl-Slider mit Jahresmarkierungen.
- Performance-Ziel: P95 < 200 ms bei 365-Tages-Window mit 1000
  Items.

## 5. Permissions

`asset.timeline.view` — wer das Asset sehen darf (siehe MVP-035 §8).
Customer-Portal: filtert Items deren Quelle nicht
kundenportal-sichtbar ist (z. B. interne Audit-Events `health.*` mit
`severity = critical` werden ausgeblendet, dafür ein generisches
„Wartung durchgeführt"-Item angezeigt).

## 6. Audit

Timeline-Aufruf selbst wird **nicht** geloggt (Lesezugriff). Wenn
ein Item geöffnet wird (Drilldown), greift das Audit der jeweiligen
Quelle.

## 7. Akzeptanzkriterien

1. `AssetTimelineBuilder` aggregiert mindestens die 6 Quellen aus
   §2 mit korrekten Mappings auf `TimelineItem`.
2. Cursor-Paginierung deterministisch.
3. Cache invalidiert bei jeder relevanten Mutation (Event-Listener
   `AssetTimelineCacheInvalidator`).
4. UI rendert 500 Items < 300 ms Browser-Rendering.
5. Customer-Portal-Filter §5 mit Tests.

## 8. Out-of-scope (MVP-037)

- Vergleichs-Sicht zwei Assets nebeneinander — Folge.
- Export der Timeline als PDF/CSV — kommt mit MVP-043.

## 9. Folge

- MVP-038 Defekt-/Sperrstatus (eigene UI-Komponente, die Timeline
  erweitert).
