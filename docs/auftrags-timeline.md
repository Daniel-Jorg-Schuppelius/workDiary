# Auftragsverlauf als Timeline

Status: Aktiv (MVP-010, Issue #10) • Quellen:
[Feature 001 — Aufzeichnung und Zeiterfassung als Kernprodukt](features/001-zeiterfassung-kernprodukt.md),
[Feature 023 — Suche, Timeline und Fallakte](features/023-suche-timeline-fallakte.md).
• Begleitend:
[UX-Pattern-Katalog](ux-pattern-katalog.md) §3.6,
[Status- und Aktionsglossar](status-aktionsglossar.md) §3.1/§4.1,
[Accessibility-Checkliste](accessibility-checkliste.md).

## 1. Zweck

Die **Auftrags-Timeline** stellt für jeden Auftrag (`DiaryEntry`) **alle
relevanten Ereignisse in chronologischer Reihenfolge** dar. Sie ist das
Rückgrat der späteren **Fallakte** (MVP-013) und liefert Außendienst,
Teamleitung und Auftraggebenden eine einheitliche, durchsuchbare,
manipulationssichere Historie eines Auftrags.

Ziele:

- **Vollständigkeit:** kein relevanter Schritt fehlt, jede Änderung ist
  nachvollziehbar.
- **Lesbarkeit:** ein Blick genügt, um „was ist passiert, wann, durch wen"
  zu beantworten.
- **Anschlussfähigkeit:** dieselbe Komponente wird später für die
  **Objekt-/Asset-Timeline** (MVP-037) und die **Kunden-Timeline**
  wiederverwendet.

## 2. Geltungsbereich

| Bereich                  | Timeline?                                   |
| ------------------------ | ------------------------------------------- |
| Auftrag (`DiaryEntry`)   | ✅ Pflicht — Detailseite §3.6 Pkt. 7         |
| Kunde                    | später (MVP-014 Suche, eigenständige Sicht) |
| Projekt                  | später (Folge-MVP)                          |
| Asset/Objekt             | ✅ ab MVP-037 (gleiche Komponente)           |
| Prozedur-Lauf            | später (`ProcedureRun` history)             |

## 3. Ereignistypen (kanonisch)

Alle Ereignisse werden über einen einheitlichen **Read-Model-Eintrag** in
die Timeline gespeist. Quelle ist primär `audit_logs`, ergänzt um Daten aus
`timesheets`, `comments`, `attachments`, `protocols` und `procedure_runs`.

| Typ-Schlüssel              | Icon            | Tone        | Bedeutung / Quelle                                                       |
| -------------------------- | --------------- | ----------- | ------------------------------------------------------------------------ |
| `order.created`            | `add`           | `info`      | Auftrag angelegt (`audit:order.created`).                                |
| `order.status_changed`     | `flag`          | je Tone     | Statuswechsel (`audit:order.status_changed`); Tone aus Ziel-Status.      |
| `order.assigned`           | `person_add`    | `info`      | Zuweisung Bearbeitende(r) / Team.                                        |
| `order.unassigned`         | `person_remove` | `ghost`     | Zuweisung entfernt.                                                      |
| `order.scheduled`          | `event`         | `info`      | Termin/Slot geplant oder verschoben.                                     |
| `order.priority_changed`   | `priority_high` | `warning`   | Priorität geändert.                                                      |
| `order.field_changed`      | `edit`          | `ghost`     | Beliebige andere Feldänderung (Beschreibung, Tags) — gruppiert.          |
| `time.started`             | `play_circle`   | `primary`   | Stoppuhr gestartet (`timesheets`).                                       |
| `time.stopped`             | `stop_circle`   | `primary`   | Stoppuhr beendet.                                                        |
| `time.entry_added`         | `schedule`      | `primary`   | Manueller Zeiteintrag erfasst.                                           |
| `time.entry_corrected`     | `edit_calendar` | `warning`   | Korrekturantrag genehmigt.                                               |
| `time.submitted`           | `outbox`        | `primary`   | Zeit eingereicht.                                                        |
| `time.approved`            | `check_circle`  | `success`   | Zeit genehmigt.                                                          |
| `time.rejected`            | `cancel`        | `error`     | Zeit abgelehnt.                                                          |
| `comment.added`            | `chat_bubble`   | `ghost`     | Kommentar.                                                               |
| `attachment.added`         | `attach_file`   | `ghost`     | Anhang hochgeladen.                                                      |
| `attachment.removed`       | `delete`        | `error`     | Anhang entfernt.                                                         |
| `protocol.created`         | `description`   | `info`      | Abnahmeprotokoll angelegt.                                               |
| `protocol.submitted`       | `outbox`        | `primary`   | Protokoll vorgelegt.                                                     |
| `protocol.accepted`        | `verified`      | `success`   | Abgenommen (clean).                                                      |
| `protocol.accepted_defects`| `verified`      | `warning`   | Abgenommen mit Mängeln.                                                  |
| `protocol.rejected`        | `cancel`        | `error`     | Abgelehnt.                                                               |
| `procedure.started`        | `playlist_play` | `primary`   | Prozedur-Lauf gestartet.                                                 |
| `procedure.completed`      | `task_alt`      | `success`   | Prozedur-Lauf abgeschlossen.                                             |
| `procedure.aborted`        | `cancel`        | `error`     | Prozedur-Lauf abgebrochen.                                               |
| `communication.added`      | `forum`         | `ghost`     | Kommunikationsnotiz (MVP-012).                                           |
| `open_issue.opened`        | `error_outline` | `warning`   | Offener Punkt eröffnet (Protokoll).                                      |
| `open_issue.resolved`      | `check_circle`  | `success`   | Offener Punkt geschlossen.                                               |
| `customer.signed`          | `draw`          | `success`   | Kundenunterschrift.                                                      |
| `notification.sent`        | `mail`          | `ghost`     | Benachrichtigung verschickt (read-only, ausblendbar).                    |

Aktionsverben in Beschreibungen folgen dem
[Status- und Aktionsglossar](status-aktionsglossar.md).

## 4. Eintragsstruktur

Jeder Timeline-Eintrag enthält die folgenden Felder. Sie werden im
Read-Model `OrderTimelineEntry` zusammengeführt und an die Blade-Komponente
`<x-timeline>` (siehe §6) übergeben.

| Feld           | Typ                                 | Pflicht | Beschreibung                                                                 |
| -------------- | ----------------------------------- | :-----: | ---------------------------------------------------------------------------- |
| `id`           | string (stabile Kennung)            |   ✅    | z. B. `audit:1234` oder `timesheet:99` — für Anker, Drilldown, Idempotenz.   |
| `occurred_at`  | `DateTimeImmutable` (UTC)           |   ✅    | Zeitpunkt des Ereignisses.                                                   |
| `type`         | string (Schlüssel aus §3)           |   ✅    | Steuert Icon, Tone, Beschreibungstext.                                       |
| `actor`        | `User` / System / „Kunde"           |   ✅    | Wer hat das ausgelöst.                                                       |
| `title`        | string                              |   ✅    | 1 Zeile, deutsch, ohne HTML.                                                 |
| `description`  | string / Markdown (sicher escaped)  |   —    | Optionale 1–3 Zeilen Detail (z. B. Diff "Offen → In Bearbeitung").           |
| `changes`      | array (Feld → {alt, neu})           |   —    | Strukturierter Diff (aus `audit_logs.changes`).                              |
| `target`       | Eloquent-Model + Route              |   —    | Verlinkt auf Originalobjekt (Drill-down).                                    |
| `attachments`  | array<Attachment>                   |   —    | Bei Foto-Ereignissen Vorschau direkt im Eintrag.                             |
| `meta`         | array                               |   —    | Zusatzfelder pro Typ (Dauer bei Zeit, IP bei Support-Aktionen).              |
| `visibility`   | enum {`internal`, `customer`}       |   ✅    | Steuert Sichtbarkeit für die Kundenrolle (MVP-056 / `kunde`-Portal).         |

## 5. Datenquellen und Aufbau

Die Timeline ist **read-only** und wird **on demand** zusammengestellt — sie
**dupliziert keine Daten**.

```text
                     ┌────────────────────────────────┐
                     │      DiaryEntry::id = 42       │
                     └───────────────┬────────────────┘
                                     │
            ┌──────────┬─────────────┼─────────────┬──────────────┐
            ▼          ▼             ▼             ▼              ▼
       audit_logs  timesheets    comments    attachments     protocols
       (Statuswechsel, (Start/Stop, (Notizen, (Foto, PDF,    (created,
        Feldänderung,   Submit,     Antworten) Vertrag…)      submitted,
        Zuweisungen)    Approve,                              accepted, …)
                        Reject)
                                     │
                                     ▼
                       App\Services\OrderTimelineBuilder
                       ──────────────────────────────────
                       sortiert nach occurred_at DESC
                       wendet Sichtbarkeitsfilter an
                       gruppiert Mehrfach-Events (siehe §7)
                       liefert Collection<OrderTimelineEntry>
                                     │
                                     ▼
                            <x-timeline :entries="…" />
```

Pflichten an die Quellen:

1. **Jeder schreibende Service** (OrderService, TimesheetService,
   ProtocolService, ProcedureService, CommentService, AttachmentService,
   CommunicationService) erzeugt einen `audit_logs`-Eintrag mit
   `event`-Schlüssel aus §3.
2. `audit_logs.changes` enthält bei Feldänderungen `{field: {old, new}}`.
3. `auditable_type` / `auditable_id` zeigen auf das Originalobjekt (für
   Drill-down).

## 6. UI-Komponente `<x-timeline>`

Neue Blade-Komponente unter
`resources/views/components/timeline/` (Folge-Implementierungs-MVP):

- `<x-timeline :entries="$entries" :group-by="'day'" />`
- Jeder Eintrag rendert `<x-timeline.item>` mit:
  - Datum links (sticky bei langen Listen),
  - Icon + Tone-Punkt mittig (vertikale Linie),
  - Titel + Aktor rechts oben, Beschreibung darunter, Diff als
    `<x-table.diff>` (klein, klappbar).
- **Filterleiste** (`<x-filter-bar>`): Zeitraum, Typ-Auswahl (Mehrfach),
  Aktor, „Nur kundenrelevant".
- **Empty-State** über `<x-empty-state icon="history" title="Noch keine Ereignisse"/>`.
- **Tastatur**: Eintrag fokussierbar (`tabindex=0`), Enter öffnet
  Drill-down auf Originalobjekt; `Esc` schließt aufgeklappte Diffs
  (siehe [Accessibility-Checkliste](accessibility-checkliste.md)).
- **Mobile**: vertikales Layout in 1 Spalte; Datum als Sub-Header über dem
  Eintrag statt links.
- **Druck** (PDF-Fallakte, später): `print:`-Variante ohne Filterleiste,
  ohne Icons, mit `border-b` zwischen Tagen.

### Beispiel-Markup (Soll)

```blade
<x-card>
    <x-slot:header>
        <h2 class="text-base font-semibold">{{ __('Verlauf') }}</h2>
        <x-filter-bar :action="route('diary.show', $entry)" method="GET" :reset="route('diary.show', $entry)">
            <x-date-range from="$from" to="$to" />
            <x-filter-field :label="__('Typ')" for="types">
                <x-select-multi name="types[]" :options="$typeOptions" :selected="$selectedTypes"/>
            </x-filter-field>
            <x-filter-field :label="__('Nur kundenrelevant')" for="customer_only">
                <input type="checkbox" name="customer_only" value="1" @checked($customerOnly)>
            </x-filter-field>
        </x-filter-bar>
    </x-slot:header>

    <x-timeline :entries="$timeline" group-by="day">
        <x-empty-state icon="history" :title="__('Noch keine Ereignisse')" />
    </x-timeline>
</x-card>
```

## 7. Gruppierung und Performance

- **Gruppierung**:
  - Mehrere identische Feldänderungen am selben Auftrag innerhalb von
    **2 Minuten** durch denselben Aktor werden zu **einem** Eintrag
    zusammengefasst (z. B. Tag-Editierung).
  - `time.started` + `time.stopped` werden bei gleichem Aktor zu
    `time.entry_added` zusammengefasst, sobald der Eintrag existiert.
- **Pagination**: serverseitig, 50 Einträge pro Seite, Lade-Button
  „Frühere Ereignisse" am unteren Rand (kein Infinite-Scroll im MVP).
- **Index-Pflicht**: `audit_logs (auditable_type, auditable_id, occurred_at desc)`.
- **Cache**: pro Auftrag + Filter-Hash für **60 s** im Request-Cache;
  Invalidierung bei jedem Schreibvorgang auf den Auftrag oder einer
  abhängigen Quelle.

## 8. Sichtbarkeit und Rechte

| Permission                | Sichtbar                                                |
| ------------------------- | ------------------------------------------------------- |
| `diary.show` (Eigentümer) | Eigene Auftrags-Timeline, alle Einträge `internal`+`customer`. |
| `diary.show.team`         | Teammitglieder.                                         |
| `diary.show.organization` | Org-Admin / Teamleitung.                                |
| `customer-portal`         | Nur Einträge mit `visibility = customer`.               |
| `audit-log.view`          | Zusätzlich technische Felder (IP, Useragent) sichtbar.  |

Pflicht: Jede Quelle markiert beim Schreiben die `visibility`. Default ist
`internal`; Statuswechsel und Abnahmen sind `customer`.

## 9. Audit und Manipulationssicherheit

- Die Timeline selbst schreibt **nichts**. Sie liest aus
  `audit_logs` und Originaltabellen.
- `audit_logs`-Einträge sind **append-only** (keine `update`/`delete`-Route
  in Controllern; Eloquent-Observer verbietet `updating`/`deleting`).
- Löschen eines Originalobjekts (z. B. Kommentar) erzeugt einen
  `*.removed`-Audit-Eintrag, nicht das Verschwinden des Timeline-Punkts.
- Export der Timeline als CSV/PDF (Folge-MVP MVP-043) protokolliert
  `audit.exported`.

## 10. Akzeptanzkriterien (für die Umsetzung)

1. Auftragsdetailseite zeigt eine Timeline mit allen in §3 gelisteten
   Ereignissen.
2. Jeder Eintrag ist auf das Originalobjekt verlinkt (Drill-down).
3. Filter nach Zeitraum, Typ, Aktor und „kundenrelevant" funktionieren
   serverseitig.
4. Tastaturbedienung gemäß [Accessibility-Checkliste](accessibility-checkliste.md)
   ist erfüllt (Tab, Enter, Esc).
5. Kunden sehen ausschließlich Einträge mit `visibility = customer`.
6. Beim Stresstest mit 10 000 `audit_logs` für einen Auftrag liefert die
   erste Seite (50 Einträge) in < 300 ms.
7. Keine Hard-Coded Statustexte oder Tones — alle aus
   [Status- und Aktionsglossar](status-aktionsglossar.md).

## 11. Out-of-scope (MVP-010)

- Kunden-Timeline und Projekt-Timeline (eigene Folge-MVPs).
- Real-time-Updates (WebSocket/SSE) — Polling bei Bedarf später.
- KI-/LLM-Zusammenfassung des Auftragsverlaufs.
- Globale Volltextsuche über Timeline-Inhalte (MVP-014).
- Editierbare Einträge — bewusst nicht.

## 12. Folge-MVPs

- **MVP-013** Auftrag als Fallakte — bindet diese Timeline als
  Hauptsektion ein.
- **MVP-037** Objekt-Timeline — gleiche Komponente, andere Quellen.
- **MVP-042** Drill-down von Reports auf Auftragslisten → Timeline.
- **MVP-043** CSV/PDF-Export der Timeline.

## 13. Änderungsverfahren

1. Neue Ereignistypen werden zuerst in §3 dokumentiert.
2. Erst danach den `audit_logs`-Event-Schlüssel im jeweiligen Service
   schreiben und in `OrderTimelineBuilder` mappen.
3. Renaming bestehender Typ-Schlüssel erzeugt eine Migration
   (`audit_logs.event` rewriten) — Idempotenz-Schutz: alter Schlüssel
   bleibt als Alias im Builder, bis die Migration grün ist.
