# UX-Pattern-Katalog

Status: Aktiv (MVP-006, Issue #6) • Quelle:
[Feature 037 — Einheitliche Bedienung und UX-Konventionen](features/037-einheitliche-bedienung-ux-konventionen.md)
• Begleitend: [UI-Vereinheitlichung — Seiten-Audit](ui-unification-audit.md).

Dieser Katalog ist die **verbindliche Referenz für alle neuen Views** in
WorkDiary. Er beschreibt die wiederverwendbaren Blade-Komponenten, die
Aktions-, Status- und Farbsemantik sowie die Konventionen für Detailseiten,
Anhänge und Kommentare. Jede Neuentwicklung folgt diesen Mustern; Abweichungen
sind im Pull Request explizit zu begründen und in der UI-Review-Checkliste
(Abschnitt 11) zu dokumentieren.

Die hier verlinkten Komponenten liegen unter
[`resources/views/components/`](../resources/views/components/) und sind
über das `<x-…>`-Schema in Blade verfügbar.

## 1. Adressaten und Geltungsbereich

| Adressat                | Nutzung                                                            |
| ----------------------- | ------------------------------------------------------------------ |
| **Entwickler**          | Vor jedem neuen Feature: Pattern wählen, nicht neu erfinden.       |
| **Reviewer**            | UI-Review-Checkliste (§11) abarbeiten, sonst Block-Kommentar.      |
| **Designer / PO**       | Vorhandene Bausteine als Grundlage für Mockups.                    |
| **Legacy-Bereich**      | **Nicht** anwendbar — Legacy-Views bleiben unverändert.            |

## 2. Bediengrundsätze (verbindlich)

Aus Feature 037 in verbindliche Regeln überführt:

1. **Eine Aktion — ein Name — ein Icon.** Siehe Aktions-Glossar §6.
2. **Eine Statuslogik.** Status-Tones folgen §7.
3. **Eine Listen-Anatomie.** Toolbar → Filterleiste → Tabelle/Karten → Leerzustand → Pagination (§3.1).
4. **Eine Formular-Anatomie.** Modal-first, Pflichtfelder oben, Speichern/Abbrechen/Löschen feste Reihenfolge (§3.3).
5. **Eine Detailseiten-Anatomie.** Header → Hauptdaten → Sekundärdaten → Kommentare → Anhänge → Historie (§3.6).
6. **Leere Zustände sind keine Fehler.** `<x-empty-state>` mit Handlungsaufforderung (§5).
7. **Deutsch ausschließlich.** Alle Labels und Microcopy in `lang/de/`.
8. **Mobil immer mindestens nutzbar.** Toolbar wrap-fähig, Tabellen mit Horizontal-Scroll, Modals als Bottom-Sheet bei < 640 px (§9).

## 3. Pattern-Katalog

### 3.1 Listenseite (Index)

Standard-Anatomie:

```blade
<x-page-shell gap="4" overflow="auto">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('Mitglieder')">
            <x-slot:actions>
                <x-icon-btn icon="add" :href="route('org.members.create')" :label="__('Neu')" tone="primary" show-label />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('org.members._filter_bar')   {{-- siehe §3.2 --}}

    <x-card>
        <x-table table-sort="server" :route="route('org.members.index')"
                 :current-sort="$sort" :current-dir="$dir" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="role">{{ __('Rolle') }}</x-table.th>
                    <x-table.th align="right">{{ __('Aktionen') }}</x-table.th>
                </tr>
            </x-slot:head>

            @forelse ($members as $member)
                <tr>
                    <td>{{ $member->name }}</td>
                    <td><x-status-badge :label="$member->role->label()" tone="info" size="xs" /></td>
                    <td class="text-right">
                        <x-action-buttons
                            :show-route="route('org.members.show', $member)"
                            :edit-route="route('org.members.edit', $member)"
                            :delete-route="route('org.members.destroy', $member)" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="3" icon="group" :title="__('Noch keine Mitglieder')" compact />
            @endforelse
        </x-table>

        <x-pagination-info :paginator="$members" show-links />
    </x-card>
</x-page-shell>
```

Referenzimplementierungen: [resources/views/org/members/index.blade.php](../resources/views/org/members/index.blade.php),
[resources/views/archive/index.blade.php](../resources/views/archive/index.blade.php).

### 3.2 Filterleiste

Listenseiten verwenden **immer** `<x-filter-bar>`. Filterfelder werden über
`<x-filter-field>` umschlossen, Zeiträume immer mit `<x-date-range>`:

```blade
<x-filter-bar :action="route('duties.index')" method="GET" :reset="route('duties.index')">
    <x-filter-field :label="__('Suche')" for="q" class="flex-1 min-w-52">
        <input type="text" id="q" name="q" value="{{ request('q') }}" class="input input-sm w-full">
    </x-filter-field>

    <x-filter-field :label="__('Zeitraum')" for="from">
        <x-date-range from-name="from" to-name="to" type="date"
                      :from="request('from')" :to="request('to')" layout="join" />
    </x-filter-field>

    <x-filter-field :label="__('Status')" for="status">
        <select id="status" name="status" class="select select-sm">
            <option value="">{{ __('Alle') }}</option>
            <option value="open" @selected(request('status') === 'open')>{{ __('Offen') }}</option>
        </select>
    </x-filter-field>
</x-filter-bar>
```

Regeln:

- Filter sind **GET**, niemals POST.
- „Zurücksetzen" wird durch `:reset` automatisch eingehängt.
- Mehr als sechs Felder ⇒ zweite Zeile per `flex-wrap`, kein Akkordeon.
- Filter werden **nicht** in einen Modal verpackt.

### 3.3 Eingabe-Modal (Create / Edit)

Eingaben erfolgen grundsätzlich in einem Modal. Ausnahmen sind in
[`docs/ui-unification-audit.md`](ui-unification-audit.md#ausnahmen-bewusst-inline)
namentlich genannt (Stundenzettel-Detail, Importseiten, Reports-Filter,
Audit-Listing).

Konvention: pro Resource ein Partial `…/_form_dialog.blade.php` mit `<x-modal>`
plus ein neutrales `_form_body.blade.php` mit den eigentlichen Feldern, das
auch in inline-Seiten wiederverwendet werden kann.

```blade
{{-- resources/views/invoices/_form_dialog.blade.php --}}
<x-modal
    id="invoice-modal"
    :title="$invoice->exists ? __('Rechnung bearbeiten') : __('Rechnung anlegen')"
    icon="receipt_long"
    tone="primary"
    :action="$invoice->exists ? route('invoices.update', $invoice) : route('invoices.store')"
    :method="$invoice->exists ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    @include('invoices._form_body', ['invoice' => $invoice])

    @if ($invoice->exists)
        <x-slot:footerExtra>
            <x-icon-btn icon="delete" tone="error" :href="route('invoices.destroy', $invoice)"
                        :label="__('Löschen')" data-confirm="{{ __('Wirklich löschen?') }}" />
        </x-slot:footerExtra>
    @endif
</x-modal>
```

Trigger: Listen-Buttons öffnen Modale über `data-entry-modal-trigger`
(JS-Konvention, siehe `resources/js/entry-modal.js`).

### 3.4 Formular-Anatomie

Felder werden in `<x-form-group>`-Blöcken gruppiert. Reihenfolge: **Identität →
Inhalt → Zeit → Beteiligte → Metadaten**.

```blade
<x-form-group :legend="__('Allgemein')" icon="info" cols="2">
    <label class="form-control">
        <span class="label-text">{{ __('Titel') }} <span class="text-error">*</span></span>
        <input type="text" name="title" required value="{{ old('title', $entry->title) }}" class="input">
    </label>

    <label class="form-control">
        <span class="label-text">{{ __('Kunde') }}</span>
        <select name="customer_id" class="select"> … </select>
    </label>
</x-form-group>
```

Footer-Reihenfolge in jedem Modal: **Abbrechen — (Löschen via `footerExtra`) — Speichern**.

### 3.5 Bulk-Aktionen

Listen mit Mehrfachauswahl verwenden `<x-bulk-toolbar>` plus die
`bulk-selection.js`-Konvention:

```blade
<form data-bulk-form method="POST" :action="route('invoices.bulk')">
    @csrf
    <input type="checkbox" data-bulk-select-all class="checkbox checkbox-sm">

    <x-bulk-toolbar root-selector="[data-bulk-form]" input-name="ids" tone="primary" icon="checklist">
        <x-slot:actions>
            <x-icon-btn icon="archive" type="submit" name="action" value="archive" :label="__('Archivieren')" show-label />
            <x-icon-btn icon="delete"  type="submit" name="action" value="delete"  :label="__('Löschen')"     tone="error" show-label />
        </x-slot:actions>
    </x-bulk-toolbar>

    {{-- pro Zeile: --}}
    <input type="checkbox" data-bulk-checkbox name="ids[]" value="{{ $invoice->id }}" class="checkbox checkbox-sm">
</form>
```

Die Toolbar ist sticky und blendet sich automatisch ein/aus.

### 3.6 Detailseite (Show)

Verbindliche Reihenfolge:

1. **Toolbar** (`<x-page-toolbar>`) mit Titel, Status-Badge und Aktionen
   (Edit / Archive / Restore / Delete in dieser Reihenfolge).
2. **Hauptdaten-Card** — Inhalt, Beschreibung, Markdown.
3. **Sekundärdaten** — Tags, Beteiligte, Verknüpfungen.
4. **Metadaten-Grid** — Zeitraum, Erstellt-/Geändert-Daten (kleine Tiles).
5. **Kommentare** — `@include('comments._thread', […])`.
6. **Anhänge** — `@include('attachments._panel', […])`.
7. **Historie / Audit** (read-only Liste, falls Permission `audit-log.view`).

Referenz: [resources/views/diary/_show_body.blade.php](../resources/views/diary/_show_body.blade.php).

### 3.7 Anhänge

```blade
@include('attachments._panel', [
    'attachable' => $entry,
    'attachments' => $entry->attachments,
    'uploadRoute' => route('attachments.store'),
    'canEdit' => $canEdit,
])
```

Konvention:

- Icon je MIME (`image`, `picture_as_pdf`, `attach_file`).
- Anzeige: Dateiname (Link auf signierte URL via
  `AttachmentController::downloadUrl()`), Größe, Uploader, Zeit, Delete.
- Upload-Form direkt unter der Liste (Drag-and-Drop optional, kein Modal).
- Leerzustand: `<x-empty-state icon="attach_file" :title="__('Keine Anhänge')" compact />`.

### 3.8 Kommentare

```blade
@include('comments._thread', [
    'commentable' => $entry,
    'comments' => $entry->comments()->latest()->get(),
    'storeRoute' => route('comments.store', [$entry::class, $entry]),
    'canComment' => $canComment,
])
```

Konvention:

- Chronologisch absteigend (neueste oben).
- Pro Kommentar: Avatar/Name · Zeitstempel · ggf. „(bearbeitet)" · Edit/Delete (eigene Kommentare oder mit `comment.moderate`).
- Eingabe als `<textarea>` plus Submit, **nicht** im Modal.
- Leerzustand: `<x-empty-state :message="__('Noch keine Kommentare.')" compact />`.

## 4. KPI-Tiles (Dashboard-Pattern)

```blade
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <x-kpi-tile :label="__('Arbeit')"   value="8:30 h" tone="primary" :href="route('today.index')" />
    <x-kpi-tile :label="__('Pausen')"   value="0:45 h" tone="info"    format="duration" />
    <x-kpi-tile :label="__('Saldo')"    value="+1:15"  tone="success" />
    <x-kpi-tile :label="__('Krank')"    value="0"      tone="ghost"   />
</div>
```

Regeln: max. vier Tiles pro Zeile, `tone` folgt §7, Klick navigiert per `:href`
auf die zugehörige Detail-/Listenseite.

## 5. Leer-, Lade- und Fehlerzustände

| Zustand     | Komponente / Pattern                                      | Beispiel                                                                              |
| ----------- | --------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Leer (Karte) | `<x-card><x-empty-state … /></x-card>`                    | „Noch keine Einträge — *Eintrag anlegen*"                                            |
| Leer (Tabelle) | `<x-table.empty :colspan="…" icon="…" :title="…" compact />` | Innerhalb `@forelse … @empty`                                                       |
| Laden       | Browser-Default (kein Spinner-Overlay als Pflicht); `disabled` an Submit-Buttons | Submit-Button bekommt `loading`-Class via Alpine/JS bei Klick. |
| Fehler      | Flash-Message in `<x-page-shell>` plus inline Validation | `lang/de/validation.php`                                                              |
| Erfolg      | Flash-Message `success`                                   | Wird via `<x-page-shell>` aus Session geladen.                                        |

**Pflicht:** Jeder Leerzustand enthält eine Handlungsaufforderung (CTA), kein bloßes „Nichts da".

## 6. Aktions-Glossar

Verbindliche Labels und Icons (Google Material Symbols, Outlined):

| Aktion             | Label          | Icon              | Tone (Default) |
| ------------------ | -------------- | ----------------- | -------------- |
| Anlegen            | „Neu"          | `add`             | `primary`      |
| Speichern          | „Speichern"    | `save`            | `primary`      |
| Abbrechen          | „Abbrechen"    | `close`           | `ghost`        |
| Bearbeiten         | „Bearbeiten"   | `edit`            | `ghost`        |
| Anzeigen           | „Anzeigen"     | `visibility`      | `ghost`        |
| Löschen            | „Löschen"      | `delete`          | `error`        |
| Archivieren        | „Archivieren"  | `archive`         | `warning`      |
| Wiederherstellen   | „Wiederherstellen" | `restore`     | `info`         |
| Freigeben          | „Freigeben"    | `check_circle`    | `success`      |
| Sperren            | „Sperren"      | `lock`            | `warning`      |
| Entsperren         | „Entsperren"   | `lock_open`       | `info`         |
| Export             | „Exportieren"  | `download`        | `secondary`    |
| Import             | „Importieren"  | `upload`          | `secondary`    |
| Anhang hinzufügen  | „Anhang"       | `attach_file`     | `ghost`        |
| Kommentieren       | „Kommentieren" | `chat_bubble`     | `ghost`        |
| Suche              | „Suche"        | `search`          | `ghost`        |
| Filter zurücksetzen| „Zurücksetzen" | `restart_alt`     | `ghost`        |
| Mitglied vertreten | „Vertreten"    | `support_agent`   | `warning`      |

Abweichungen sind nur sinnvoll, wenn der Fachkontext einen anderen Begriff
verbindlich verlangt (z. B. „Rechnung stellen" statt „Speichern"). In dem
Fall: gleiche Icon-/Tone-Wahl beibehalten.

## 7. Status- und Farbsemantik

| Tone         | Bedeutung                                | Beispiel                                       |
| ------------ | ---------------------------------------- | ---------------------------------------------- |
| `primary`    | aktive Hauptaktion, „im Fluss"           | „Offen", „In Bearbeitung"                      |
| `secondary`  | neutrale Sekundär-Aktion                 | „Export", „Drucken"                            |
| `accent`     | hervorgehobene Spezialfunktion           | „Live", „Empfohlen"                            |
| `success`    | erfolgreich abgeschlossen / freigegeben  | „Abgeschlossen", „Freigegeben", „Bezahlt"      |
| `warning`    | benötigt Aufmerksamkeit, nicht kritisch  | „Wartet auf Prüfung", „Bald fällig"            |
| `error`      | Fehler, gesperrt, abgelehnt              | „Abgelehnt", „Gescheitert"                     |
| `info`       | informativ, neutraler Status             | „Geplant", „Pausiert"                          |
| `ghost`      | passiver / sehr neutraler Zustand        | „Entwurf", „Inaktiv"                           |
| `neutral`    | rein dekorativ                           | Zähler-Badges                                  |

Tones gelten gleichermaßen für `<x-status-badge>`, `<x-kpi-tile>` und
`<x-icon-btn>`. **Andere Farben sind verboten**, insbesondere keine
projekt-spezifischen Tailwind-Klassen direkt im Template.

## 8. Icons

- Set: **Google Material Symbols, Outlined** (kein Mix mit Heroicons,
  FontAwesome u. ä.).
- Verwendung: `<x-icon name="edit" />` oder im Notfall
  `<span class="material-symbols-outlined">edit</span>`.
- Icon-only-Buttons benötigen immer `:label="…"` für Screenreader und Tooltip.

## 9. Mobile Pattern

- **Bottom-Sheet statt Modal** unter 640 px (regelt `<x-modal>` automatisch).
- **Filterleiste** wird per `flex-wrap` umbrochen; ab vier Filtern auf Mobile
  optional als „Filter"-Button mit Drawer (Folge-MVP, kein Pflichtmuster heute).
- **Tabellen** mit horizontalem Scroll (`overflow-x-auto`), nicht in Karten
  umgebrochen.
- **Toolbar-Aktionen** unter 640 px nur Icon (Label wird ausgeblendet, Tooltip
  bleibt).
- **Erfassung** (Zeit, Foto, Unterschrift): primärer CTA immer am unteren
  Bildschirmrand erreichbar (max. 48 px Hand-Reichweite).

## 10. Barrierefreiheit

Kurzfassung; verbindliche Vollversion in
[docs/accessibility-checkliste.md](accessibility-checkliste.md).

- Jeder Icon-Button trägt `:label`. Tooltip + `aria-label` werden von
  `<x-icon-btn>` gesetzt.
- Tab-Reihenfolge folgt visueller Reihenfolge; keine `tabindex="-1"` ohne
  Grund.
- Farben sind **nie** alleiniger Statusträger — immer Tone + Text.
- Modal-Trigger und -Schließer reagieren auf `Enter` / `Esc`.
- Pflichtfelder werden mit `<span class="text-error">*</span>` und `required`
  ausgezeichnet.

## 11. UI-Review-Checkliste

Vor dem Merge eines Pull Requests, der Views ändert oder hinzufügt:

- [ ] `<x-page-shell>` als Außencontainer (außer dokumentierte Ausnahme).
- [ ] `<x-page-toolbar>` mit Titel und ggf. Aktions-Slot vorhanden.
- [ ] Filter über `<x-filter-bar>` / `<x-filter-field>`; GET-Form.
- [ ] Tabellen über `<x-table>` mit `<x-table.th>` für sortierbare Spalten
      und `<x-table.empty>` für Leerzustand.
- [ ] Eingaben in `<x-modal>` (Ausnahme dokumentiert).
- [ ] `<x-status-badge>` mit Tone aus §7.
- [ ] Aktions-Labels und -Icons aus §6.
- [ ] Anhänge über `attachments._panel`, Kommentare über `comments._thread`.
- [ ] Detailseite folgt Reihenfolge §3.6.
- [ ] Leerzustände mit `<x-empty-state>` und CTA.
- [ ] Mobile-Check: Toolbar wrap, Modal als Bottom-Sheet, Icons-only ab 640 px.
- [ ] Keine direkten Farbklassen (`bg-red-500`, `text-blue-700` …) im Template.
- [ ] Texte ausschließlich in `lang/de/`, keine englischen Inline-Strings.
- [ ] Keine Imports aus `App\Legacy\*` außer in dokumentierten Bridges
      (siehe Legacy-Audit in [`ui-unification-audit.md`](ui-unification-audit.md#legacy-audit)).

## 12. Out of scope

Folgende Punkte aus Feature 037 sind **nicht** Teil dieses MVP und kommen
über separate Folge-Tickets:

- Storybook- / Pattern-Preview als interaktive Komponente.
- Usability-Tests mit echten Rollen.
- Keyboard-Shortcuts für Power-User.
- Geführte Onboarding-Flows pro Rolle.
- Rollenbasierte Startseiten.

## 13. Änderungsverfahren

Änderungen an diesem Katalog erfordern:

1. Pull Request mit Diff auf dieses Dokument **und** gleichzeitige
   Aktualisierung von [`ui-unification-audit.md`](ui-unification-audit.md),
   falls sich Status oder Konvention einer Seite ändert.
2. Bei neuen Komponenten: Erweiterung von §3 plus Hinzufügen der
   Komponente in [`resources/views/components/`](../resources/views/components/).
3. Bei neuen Aktionen / Status-Tones: Erweiterung von §6 bzw. §7 — jede
   neue Aktion erhält genau ein Label und genau ein Icon.
4. Tests für neue interaktive Komponenten in
   `tests/Feature/Ui/…` (Sichtbarkeit pro Rolle, ARIA-Attribute, Modal-Lifecycle).
