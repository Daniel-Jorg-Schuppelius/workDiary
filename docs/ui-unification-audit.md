# UI-Vereinheitlichung — Seiten-Audit (neuer Bereich)

Diese Checkliste dokumentiert den Rollout der Vereinheitlichung. Pro Seite:

- **Shell**: Verwendet [`<x-page-shell>`](../resources/views/components/page-shell.blade.php)
- **Card**: Inhaltsblöcke verwenden [`<x-card>`](../resources/views/components/card.blade.php)
- **Table**: Verwendet [`<x-table>`](../resources/views/components/table.blade.php)
- **Empty**: Leere Datensätze über [`<x-empty-state>`](../resources/views/components/empty-state.blade.php) als graues Feld in weißer Box
- **Modal**: Eingaben in [`<x-modal>`](../resources/views/components/modal.blade.php) (Ausnahmen: Stundenzettel-Detail, Filterleisten, Importseiten)
- **Legacy-frei**: Zeigt keine Daten aus `app/Legacy/*` an

Statuswerte: ✅ erledigt · ⚠️ teilweise · ❌ offen · — nicht anwendbar

## Status pro Feature-Ordner

| Ordner                   | Shell | Card | Table | Empty | Modal | Legacy-frei | Notiz                                               |
| ------------------------ | :---: | :--: | :---: | :---: | :---: | :---------: | --------------------------------------------------- |
| `week/`                  |  ❌   |  ❌  |  ❌   |  ❌   |   —   |     ✅      | Design-Referenz, eigener Layout                     |
| `org/members/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | Erste umgestellte Seite                             |
| `timesheets/` (show)     |  ❌   |  ⚠️  |   —   |  ✅   |   —   |     ✅      | Signaturkarte umgestellt; Rest offen                |
| `timesheets/` (index)    |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `duties/`                |  ❌   |  ❌  |  ⚠️   |  ✅   |  ✅   |     ✅      | Tab-Partials nutzen `<x-table>` + `<x-empty-state>` |
| `duty-plans/`            |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `coverage-requirements/` |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `on-call-shifts/`        |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `vacations/`             |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `assignments/`           |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `projects/`              |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      | Tabs (Übersicht, Tasks, Timesheets, ...)            |
| `tasks/`                 |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `time-entries/`          |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `invoices/`              |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `milestones/`            |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `billing-rules/`         |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `customers/`             |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `customers/import/`      |  ❌   |  ❌  |  ❌   |  ❌   |   —   |     ✅      | Importseite: inline Form OK                         |
| `service-orders/`        |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `tours/`                 |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `travel-logs/`           |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `vehicles/`              |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `energy-logs/`           |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `attendance/`            |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `stopwatch/`             |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `flex/` (Gleitzeit)      |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `today/`                 |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `reports/`               |  ❌   |  ❌  |  ❌   |  ❌   |   —   |     ✅      | Filterleisten inline (gewollt)                      |
| `archive/`               |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `qualifications/`        |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `shift-types/`           |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `materials/`             |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `admin/organizations/`   |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `admin/plugins/`         |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `audit/`                 |  ❌   |  ❌  |  ❌   |  ✅   |   —   |     ✅      | Read-only Listing                                   |
| `schedule/`              |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      | Komplex (Wochenmatrix)                              |
| `kanban/`                |  ❌   |  ❌  |  ❌   |  ❌   |  ✅   |     ✅      |                                                     |
| `holidays/`              |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |
| `tags/`                  |  ❌   |  ❌  |  ❌   |  ✅   |  ✅   |     ✅      |                                                     |

## Ausnahmen (bewusst inline)

| Seite                                          | Begründung                                                       |
| ---------------------------------------------- | ---------------------------------------------------------------- |
| `timesheets/show.blade.php`                    | Stundenzettel-Einträge benötigen Platz, passen nicht in Modal    |
| `customers/import/*` und vergleichbare Imports | Mehrstufiger Upload-/Mapping-Flow                                |
| `reports/*`                                    | Filterleisten (`<x-filter-bar>`) sind keine echten Eingabe-Forms |
| `audit/index`                                  | Nur-Lesen                                                        |

## Konventionen

- Wrapper: `<x-page-shell>` (Default-Variante mit `gap-4` und `overflow-auto`).
- Toolbar: `<x-slot:toolbar>` mit `<x-page-toolbar>` darin.
- Inhalt: jede Sektion in eine eigene `<x-card>`. Tabellen-Karten verwenden direkt `<x-table>` (eigene Karte).
- Leerzustand:
    - In Tabellen: über `:empty="$collection->isEmpty()"` oder `@forelse … @empty <x-empty-state />`.
    - Sonst: `<x-card><x-empty-state … /></x-card>`.
- Formulare: `<a data-entry-modal-trigger>` öffnet Modal über `_form_dialog.blade.php`-Partial mit `<x-modal>`.

## Legacy-Audit

`php scripts/find-legacy-usage-in-new.php` listet alle `App\Legacy\*`-Referenzen außerhalb erlaubter Bridge-Stellen (`app/Legacy/`, `resources/views/legacy/`, `User`-Modell, `DiaryController`, Legacy-Migration-Controller). Ziel: 0 Treffer am Ende des Rollouts.

Bekannte bewusst stehengelassene Bridges:

- `User::isAdmin()` ruft `LegacyRoleResolver::isAdmin()` als Fallback, bis alle Admins über Spatie-Rollen verfügen.
- `DiaryController::show()` lädt `LegacyDiaryEntry` für Einträge mit `legacy_id`, um die Quelle eines importierten Eintrags anzuzeigen.
- Nav-Link „Mitarbeiter" zeigt im Legacy-Modus auf `legacy.users.index`, im neuen Modus auf `org.members.index`.
