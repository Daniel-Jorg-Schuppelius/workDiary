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

## Roadmap-Module (geplant, noch nicht implementiert)

Bereiche, die durch die MVP-Roadmap (`docs/features/github-issues-mvp.md`)
entstehen werden. Für jedes Modul gelten beim späteren Rollout die Konventionen
aus dem [UX-Pattern-Katalog](ux-pattern-katalog.md). Eintrag wechselt von ❌
auf ✅, sobald das Folge-MVP umgesetzt ist. Die Spalte „Quelle" verweist auf
das auslösende MVP-Ticket.

| Geplanter Ordner / Bereich       | Shell | Card | Table | Empty | Modal | Quelle (MVP)                | Hinweise                                                                 |
| -------------------------------- | :---: | :--: | :---: | :---: | :---: | --------------------------- | ------------------------------------------------------------------------ |
| `admin/privacy/`                 |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-005 (#5)                | Datenschutzseite für Org-Admin; Subseiten Sessions/Tokens/Integrationen/Exporte/Support |
| `admin/diagnostics/`             |  ❌   |  ❌  |  ❌   |  ❌   |   —   | MVP-044 (#43)               | Diagnose-Seite (Version, Lizenz, Queue, Scheduler, Mail, Storage, Backupstatus); read-only |
| `admin/support-report/`          |  ❌   |  ❌  |   —   |  ❌   |   —   | MVP-045 (#44)               | Supportbericht-Export (PDF/JSON, anonymisiert)                           |
| `admin/license/`                 |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-047 (#46)               | Lizenzstatus, Feature-Flags (siehe `docs/lizenzierung.md`)               |
| `admin/onboarding/`              |  ❌   |  ❌  |   —   |  ❌   |   —   | MVP-048                     | Checkliste pro neuer Organisation, Schritt-für-Schritt                   |
| `admin/imports/` (generisch)     |  —   |  —  |  —   |  —   |   —   | MVP-049                     | Mehrstufiger Upload-/Mapping-Flow → Ausnahme wie `customers/import/`     |
| `admin/demo-data/`               |  ❌   |  ❌  |   —   |  ❌   |  ❌   | MVP-050                     | Demo-Mandant einrichten/zurücksetzen                                     |
| `diary/show` als **Fallakte**    |   —   |  ❌  |   —   |   —   |   —   | MVP-013                     | Erweiterung der bestehenden Detailseite (Header/Timeline/Anhänge/Kommentare/Audit) |
| `timelines/`                     |  ❌   |  ❌  |   —   |  ❌   |   —   | MVP-010, MVP-037            | Auftrags- und Objekt-Timeline als wiederverwendbare Komponente           |
| `communications/`                |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-012                     | Kommunikationsnotizen an Auftrag / Kunde / Projekt                       |
| `today/end-of-day/`              |  ❌   |  ❌  |   —   |  ❌   |  ❌   | MVP-015                     | Tagesabschluss-Ansicht (Erweiterung von `today/`)                        |
| `monthly-approval/`              |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-016                     | Monatsfreigabe für Zeitdaten (Datenmodell-MVP, später UI)                |
| `time-corrections/`              |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-017                     | Korrekturanträge (Antrag, Prüfung, Audit)                                |
| `plan-actual/`                   |  ❌   |  ❌  |  ❌   |  ❌   |   —   | MVP-018                     | Plan/Ist-Abgleich (Anwesenheit, Projektzeit, Schicht)                    |
| `protocols/`                     |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-020 – MVP-024           | Abnahmeprotokolle inkl. Punkten, Fotos, offenen Punkten, Unterschrift    |
| `procedures/`                    |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-025 – MVP-029           | Prozeduren / Arbeitsanweisungen / Checklisten                            |
| `classifications/`               |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-030 – MVP-032           | Kernklassifikationen, Org-Kategorien, Pflichtklassifikationen            |
| `assets/`                        |  ❌   |  ❌  |  ❌   |  ❌   |  ❌   | MVP-035 – MVP-038           | Asset-/Objekt-Stammdaten, Verknüpfungen, Timeline, Defekt-/Sperr-Status  |
| `reports/customers/`             |  ❌   |  ❌  |  ❌   |  ❌   |   —   | MVP-039                     | Kundenanalyse (Aufwand, Nacharbeit, offene Punkte); Filter inline        |
| `reports/order-types/`           |  ❌   |  ❌  |  ❌   |  ❌   |   —   | MVP-040                     | Auftragstypanalyse (Plan/Ist, Durchschnittsdauer, Nacharbeit)            |
| `reports/assets/`                |  ❌   |  ❌  |  ❌   |  ❌   |   —   | MVP-041                     | Produkt-/Objektanalyse (Fehlerarten, offene Punkte)                      |
| `reports/drilldown/`             |  —   |  —  |  —   |  —   |   —   | MVP-042                     | Drill-down von Kennzahl → Auftragsliste (keine eigene Route, Querverlinkung) |
| `help/`                          |  ❌   |  ❌  |   —   |  ❌   |   —   | MVP-051                     | In-App-Hilfe-Overlays / Slide-Outs pro Modul                             |

Pflicht beim späteren Rollout pro Bereich:

1. Komponenten aus dem [UX-Pattern-Katalog](ux-pattern-katalog.md) §3 einsetzen
   (Listenseite, Filterleiste, Modal, Detailseite).
2. Status-Spalten dieses Audits beim Merge auf ✅ aktualisieren.
3. Ausnahmen (inline statt Modal) explizit in §„Ausnahmen" aufnehmen.
4. UI-Review-Checkliste (Pattern-Katalog §11) anwenden.

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
