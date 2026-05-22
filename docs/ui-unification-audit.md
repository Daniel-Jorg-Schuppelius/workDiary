# UI-Vereinheitlichung — Seiten-Audit (neuer Bereich)

Diese Checkliste dokumentiert den Rollout der Vereinheitlichung. Die
verbindlichen Bedienmuster (Komponenten, Aktions-Glossar, Status-Tones,
Detailseiten-Anatomie, UI-Review-Checkliste) sind im
[UX-Pattern-Katalog](ux-pattern-katalog.md) festgehalten (MVP-006).

Pro Seite:

- **Shell**: Verwendet [`<x-page-shell>`](../resources/views/components/page-shell.blade.php)
- **Card**: Inhaltsblöcke verwenden [`<x-card>`](../resources/views/components/card.blade.php)
- **Table**: Verwendet [`<x-table>`](../resources/views/components/table.blade.php)
- **Empty**: Leere Datensätze über [`<x-empty-state>`](../resources/views/components/empty-state.blade.php) als graues Feld in weißer Box
- **Modal**: Eingaben in [`<x-modal>`](../resources/views/components/modal.blade.php) (Ausnahmen: Stundenzettel-Detail, Filterleisten, Importseiten)
- **Legacy-frei**: Zeigt keine Daten aus `app/Legacy/*` an

Statuswerte: ✅ erledigt · ⚠️ teilweise · ❌ offen · — nicht anwendbar

> **Stand UI-Vereinheitlichung Rollout (abgeschlossen):** Alle neuen Bereichs-Seiten verwenden jetzt `<x-page-shell>` als Außencontainer. Leerzustände in Tabellen und auf Seiten verwenden `<x-empty-state>` (graues Feld in weißer `<x-card>`). Projekte werden als breite Liste mit eingerückten Unterprojekten dargestellt (Vorbild Arbeitslisten). Tabellen-Leerzustände orientieren sich an Stempeluhr/Stundenzettel.

## Status pro Feature-Ordner

| Ordner                   | Shell | Card | Table | Empty | Modal | Legacy-frei | Notiz                                               |
| ------------------------ | :---: | :--: | :---: | :---: | :---: | :---------: | --------------------------------------------------- |
| `week/`                  |  —   |  —  |  —   |  —   |   —   |     ✅      | Design-Referenz, eigener Layout                     |
| `org/members/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | Referenzimplementierung                             |
| `timesheets/` (show)     |  ✅   |  ✅  |   —   |  ✅   |   —   |     ✅      | Stundenzettel-Detail (Vorbild Empty-State)          |
| `timesheets/` (index)    |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | Stempeluhr (Vorbild leere Tabelle)                  |
| `duties/`                |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `duty-plans/`            |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `coverage-requirements/` |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `on-call-shifts/`        |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `vacations/`             |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `sick-leaves/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | Modal in `duties?tab=krank`; AU-Pflicht ab Tag 4    |
| `assignments/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `projects/`              |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | Breite Liste; Unterprojekte eingerückt              |
| `tasks/`                 |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `time-entries/`          |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `invoices/`              |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `milestones/`            |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `billing-rules/`         |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `customers/`             |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `customers/import/`      |  —   |  —  |  —   |  —   |   —   |     ✅      | Importseite (Ausnahme: mehrstufiger Upload-Flow)    |
| `tours/`                 |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | (ehem. service-orders über DiaryEntry abgelöst)     |
| `travel-logs/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `vehicles/`              |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `energy-logs/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `attendance/`            |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `stopwatch/`             |  ✅   |  ✅  |  —   |  —   |  ✅   |     ✅      |                                                     |
| `flex/` (Gleitzeit)      |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | `flex/admin` inkludiert `flex/index` (Shell dort)   |
| `today/`                 |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `reports/`               |  ✅   |  ✅  |  ✅   |  ✅   |   —   |     ✅      | Filterleisten inline (gewollt)                      |
| `archive/`               |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `qualifications/`        |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `shift-types/`           |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `materials/`             |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `admin/organizations/`   |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `admin/plugins/`         |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `audit/`                 |  ✅   |  ✅  |  ✅   |  ✅   |   —   |     ✅      | Read-only Listing                                   |
| `schedule/`              |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      | Komplex (Wochenmatrix); Import-Subflow ausgenommen  |
| `kanban/`                |  ✅   |  ✅  |  —   |  ✅   |  ✅   |     ✅      |                                                     |
| `holidays/`              |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `tags/`                  |  ✅   |  ✅  |  ✅   |  ✅   |  ✅   |     ✅      |                                                     |
| `dashboard/`             |  ✅   |  ✅  |  —   |  ✅   |   —   |     ✅      | Übersichtsseite                                     |
| `profile/api-tokens`     |  —   |  ✅  |  ✅   |  ✅   |   —   |     ✅      | Schmale, zentrierte Seite (eigener Container)       |
| `work-schedules/`        |  ✅   |  ✅  |  —   |  —   |  ✅   |     ✅      |                                                     |

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
