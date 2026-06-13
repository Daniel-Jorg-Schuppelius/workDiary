# AGENTS.md — Verbindliche Arbeitsanweisung für KI-Agenten (workDiary)

Diese Datei ist die **herstellerneutrale Single Source of Truth** für alle
automatisierten Code-Änderungen in diesem Repository. Sie gilt für jeden
KI-Assistenten (GitHub Copilot, Claude, Cursor, Codex, …). `.github/copilot-instructions.md`
verweist auf dieses Dokument; beide werden von VS Code automatisch geladen.

> **Grundsatz:** Erst die hier verlinkten verbindlichen Quellen lesen, dann
> implementieren. Vorhandene Muster werden **wiederverwendet, nicht neu erfunden**.
> Abweichungen sind im Pull Request explizit zu begründen.

---

## 1. Stack-Grundlagen

- **Backend:** Laravel 13 / PHP 8.4, `CarbonImmutable` für Datum/Zeit.
- **Frontend:** Tailwind v4 + DaisyUI v5 + Material Symbols Outlined (lokal via npm/Vite gebündelt, keine CDN-Fonts).
- **Autorisierung:** Spatie Permissions.
- **IDs in URLs:** Sqid-kodiert (Validierung dual: `Sqid::decode(...)` **plus** `is_numeric(...)`-Fallback für Legacy-Links).
- **Sprache:** Anwendungssprache ist **ausschließlich Deutsch**; alle Labels/Microcopy in `lang/de/`.

### Qualitäts-Gates (vor jedem Commit/PR)

| Zweck             | Befehl                       |
| ----------------- | ---------------------------- |
| Tests             | `composer test`              |
| Statische Analyse | `vendor/bin/phpstan analyse` |
| Code-Style        | `vendor/bin/pint`            |

---

## 2. Verbindliche Design- & UX-Quellen

Diese Dokumente sind **normativ**. Bei UI-Arbeit zuerst hier nachschlagen:

| Thema                                    | Quelle                                                               |
| ---------------------------------------- | -------------------------------------------------------------------- |
| **UX-Pattern-Katalog** (Leitdokument)    | [docs/ux-pattern-katalog.md](docs/ux-pattern-katalog.md)             |
| Status- & Aktionssemantik (Labels/Tones) | [docs/status-aktionsglossar.md](docs/status-aktionsglossar.md)       |
| Barrierefreiheit                         | [docs/accessibility-checkliste.md](docs/accessibility-checkliste.md) |
| UI-Vereinheitlichung / Ausnahmen         | [docs/ui-unification-audit.md](docs/ui-unification-audit.md)         |

**Bediengrundsätze (Kurzfassung):** Eine Aktion – ein Name – ein Icon · eine
Statuslogik · eine Listen-Anatomie · eine Formular-Anatomie (modal-first) ·
leere Zustände sind keine Fehler (immer CTA) · Deutsch · mobil mindestens
nutzbar. Details und die verbindliche Komponenten-/Farbsemantik stehen im
UX-Pattern-Katalog.

---

## 3. Index-/Listenseiten-Standard (Corporate Design)

Jede neue Index-/Listenseite (`resources/views/**/index.blade.php`) folgt diesem
Skelett. Vollständige Anatomie (Toolbar → Filter → Tabelle/Karten → Leerzustand
→ Pagination): siehe UX-Pattern-Katalog §3.1.

**Geltungsbereich:** Der Standard gilt für reguläre Listen-/Index-Seiten im
App-Layout (`@extends('layouts.app')`). **Ausgenommen** (eigenes Layout bzw. kein
Listen-Pattern): das Kundenportal (`@extends('customer.layout')`), der
Legacy-Bereich (`resources/views/legacy/**`), Vendor-Views, sowie Spezialseiten
ohne Listen-Charakter (Dashboard, Chat, Wochen-/Kalenderansichten mit
`wd-page-fill`, reine Einstellungs-/Detailformulare). Diese nutzen weiterhin
`<x-page-shell>` + `<x-page-toolbar>` o. ä.

```blade
@extends('layouts.app')

@section('title', __('Kunden'))
@section('nav-title', __('Kunden'))

@section('content')
<x-index-page :subtitle="__('Kunden des Mandanten :org verwalten.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    :href="route('customers.create')"
                    show-label>{{ __('Kunde anlegen') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Optional: Filter/Suche --}}
    <x-filter-bar :action="route('customers.index')" :reset="route('customers.index')">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
    </x-filter-bar>

    @if ($customers->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' />
    @else
        <x-table>…</x-table>
    @endif
</x-index-page>
@endsection
```

### Verbindliche Regeln

1. **Header:** `x-index-page` umschließt alles. KEIN `title`-Prop, KEIN zusätzliches `<h2>` im Body. Titel kommt aus `@section('nav-title')`.
2. **Subtitle Pflicht:** kurze Beschreibung — was wird hier verwaltet, in welchem Kontext.
3. **Aktionen:** rechte Toolbar-Aktionen ausschließlich via `<x-slot:actions>` mit `<x-icon-btn>`. Primär-Action meist „Anlegen" (`icon="add"`, `tone="primary"`, `size="sm"`, `show-label`).
4. **Filter/Suche:** wenn vorhanden `<x-filter-bar>` direkt unter der Toolbar (einzeilig, scrollbar, `sm`-Größen, Inputs/Selects mit fixer Breite `w-24`…`w-48` und `shrink-0`). **GET, niemals POST.** Wenn keine Filter: filter-bar komplett weglassen (keine leere Karte).
5. **Empty-State:** Liste/Cards `<x-empty-state framed icon="…" />`; Tabelle `<x-table.empty :colspan="…" icon="…" />` als letzte Zeile. Nur `icon` zwingend; `title`/`message` nur bei abweichendem Wording.
6. **Größen-Standard:** `input-sm` / `select-sm` / `btn-sm` für Toolbar-, Filter-
   und Formular-Eingaben. **Kein `xs`** für diese Eingaben/Buttons. **Ausnahme:**
   `xs` ist zulässig für kompakte, sekundäre Aktionen in **dichten Tabellen-/
   Listenzeilen** (Icon-only-Zeilenaktionen, Inline-Mini-Buttons) sowie für Badges
   (`size="xs"`). Faustregel: alles, was der Nutzer primär bedient (Toolbar, Filter,
   Formularfelder), ist mindestens `sm`; reine Zeilen-Mikroaktionen dürfen `xs` sein.
7. **Material Symbols** nur gültige Namen (`add`, `groups`, `calendar_month`, `menu_book`, `inbox`, …). Heroicon-Namen werden als Literal-Text gerendert.

---

## 4. Dialog-/Modal-Regeln (HART — bei JEDEM Dialog beachten)

**Dialog-first:** Eingaben werden **primär** als `<x-modal>`-Dialog umgesetzt.
`x-dialog`/`x-form-dialog` existieren **nicht mehr**. Eine **standalone Create-/
Edit-Seite** ist nur die **begründete Ausnahme** für umfangreiche Formulare —
wenn so viele Felder/Abschnitte nötig sind, dass ein Dialog unübersichtlich oder
zu hoch würde (mehrstufige Assistenten, viele Gruppen). Dann das Body-Fragment
(`_form_body.blade.php`) zwischen Seite und Dialog teilen und die Abweichung im PR
begründen (siehe `docs/ui-unification-audit.md`).

1. **Maximale Höhe** je Dialog (`--wd-dialog-max-h`, Default `78vh`) — luftig, nicht den ganzen Viewport füllen.
2. **Body scrollbar** (`overflow-y-auto`); Header & Footer bleiben sichtbar.
3. **Niemals `overflow-hidden`** auf `.modal-box` — deaktiviert Scrollen.
4. **Struktur:** `.wd-dialog` = Flex-Column mit `max-height`, ohne `overflow:hidden`; Header/Footer `shrink-0`, Body `flex-1 overflow-y-auto`. Globale Klasse `wd-dialog` zentral in `resources/css/app.css`.
5. **Gilt für** `<x-modal>` (embedded by default; standalone ad-hoc-Dialoge: `:embedded="false"` + feste `id`) und alle `<dialog class="modal">`.
6. **Header-Icon Pflicht:** `icon`-Prop ist der NAME eines Material Symbols Outlined (z. B. `event`, `person`, `description`, `delete`, `warning`, `payments`, `schedule`, `business`, `lock`, …). **Keine Emojis.** Heuristik: passt `/^[a-z0-9_]+$/` → wird via `<x-icon name>` gerendert.
7. **Keine Browser-Dialoge** (`alert()`, `confirm()`, `prompt()`, `onsubmit="return confirm(…)"`). Stattdessen:
    - Bestätigung: `data-confirm-dialog` / `window.confirmAction({ title, message, icon, label })`
    - Meldung/Fehler: `window.notifyAction({ title, message, icon, tone })`
8. **Form-Dialoge:** `<x-modal :action :method :form-data :submit-label>` nutzen — Modal rendert `<form>` automatisch (`@csrf` + Method-Spoofing), Cancel/Submit landen im sticky Footer. KEIN inline `<form>` im Default-Slot.
9. **Footer-Reihenfolge:** Abbrechen — (Löschen via `<x-slot:footerExtra>`, linksbündig) — Speichern.
10. **Form-Inputs gruppieren** mit `<x-form-group :legend icon tone [cols="1|2|3"] [:description] [compact]>`. `description` ist ein **Scalar-Prop** (kein Slot). In `cols="2|3"` mit `md:col-span-N` (nicht `sm:`) über mehrere Spalten spannen.
11. **Body-Fragmente** (`_form_body.blade.php`): nur Felder + hidden inputs + `@php`. KEIN `<form>`, KEIN Button-Block. Dialog-Wrapper (`_form_dialog.blade.php`) setzt `:action`/`:method` am `<x-modal>` und included das Body-Fragment.
12. **Alpine.js** (`x-data`, `x-effect`) im Form-Dialog NICHT am auto-`<form>`, sondern Body-Inhalt in `<div x-data x-effect class="space-y-4">` wrappen.
13. **Custom-JS-Dialoge** mit eigenem inline-`<form>`: Buttons im `<x-slot:actions>` mit `form="<form-id>"`-Attribut.
14. **Bekanntes Datum + Zeitraum → niemals `datetime-local`:** Wenn ein `date`-Feld existiert (oder das Datum aus dem Parent-Modell stammt) **und Start/Ende innerhalb dieses einen Tages (max. Tagwechsel) liegen**, Start/Ende nur als Uhrzeit (`type="time"`). FormRequest komponiert `started_at`/`ended_at` in `prepareForValidation()` (Tageswechsel: `end_time <= start_time` ⇒ +1 Tag). „Nur Start" erlaubt (offener Eintrag), „Nur Ende" via `withValidator()` ablehnen. Muster: `SaveAdminTimeEntryRequest`, `SaveTravelLogRequest`, `SaveTimesheetEntryRequest`. **Ausnahme (Zeitstempel ist primär) — `datetime-local` zulässig** für echte **mehrtägig-fähige Zeitspannen ohne beherrschendes Einzeldatum** (`ended_at => after_or_equal:started_at` ohne Selbe-Tag-Constraint): `shifts/_form_body`, `energy-logs/_form_body`, `events/_form_dialog` (Termine), `per-diem-trips/_form_body` (Dienstreisen), `assignments/_form_body` (Notdienst-Einsätze), `projects/_time_entry_dialog` (Range-Modus). Faustregel: kann der Eintrag legitim über mehrere Tage laufen ⇒ `datetime-local`; ist er an genau einen bekannten Tag gebunden ⇒ `date` + `time`.

---

## 5. CSS-/Komponenten-Konventionen

- **Tailwind v4 + DaisyUI v5.** Konfiguration in `resources/css/app.css` via `@import "tailwindcss"`, `@plugin`, `@source`, `@theme`. **Keine v3-Klassennamen:** `break-words` → `wrap-break-word`, `flex-shrink-0` → `shrink-0`, `flex-grow` → `grow`, `overflow-ellipsis` → `text-ellipsis`, `decoration-slice` → `box-decoration-slice`. Pixel-Größen wie `max-h-[600px]` möglichst durch v4-Spacing-Scale ersetzen (`max-h-150` = 600 px).
- **Farb-Tones** ausschließlich über die Semantik aus dem UX-Pattern-Katalog §7 / Status-Aktionsglossar (`primary`, `secondary`, `accent`, `success`, `warning`, `error`, `info`, `ghost`, `neutral`). **Keine projektspezifischen Tailwind-Farbklassen direkt im Template.**
- **Icons:** Google Material Symbols Outlined, via `<x-icon name="edit" />`. Icon-only-Buttons benötigen immer `:label="…"` (Screenreader + Tooltip).

### Blade-Fallstricke

- In Komponenten-Tags (`<x-…>`) funktioniert `@if (...) attr="..." @endif` **nicht** (führt zu `syntax error, unexpected token "endif"`). Stattdessen gebundenes Attribut: `:style="$cond ? '...' : null"`. In normalen HTML-Tags (`<span>`, `<td>`) ist die `@if`-Direktive im Attribut weiterhin ok.
- `&` in `__()`-Strings **einfach so** notieren — Blade `{{ }}` escapt korrekt. Niemals `&amp;` im PHP-Quellcode (sonst Doppel-Escape, sichtbar als `&amp;` im UI).

---

## 6. Komponenten-Inventar (Quellen)

- `resources/views/components/index-page.blade.php` — Skeleton-Wrapper für Index-Seiten.
- `resources/views/components/page-shell.blade.php` — Outer Container.
- `resources/views/components/page-toolbar.blade.php` — Toolbar-Karte.
- `resources/views/components/filter-bar.blade.php` — einzeilige Filterkarte.
- `resources/views/components/empty-state.blade.php` — Empty-State (mit `framed`).
- `resources/views/components/table/empty.blade.php` — Empty-Row für `<x-table>`.
- `resources/views/components/modal.blade.php` — zentrale Dialog-Komponente.
- `resources/views/components/form-group.blade.php` — Feldgruppierung in Formularen.

---

## 7. Allgemeine Regeln

- **Drilldowns** auf `customer` nutzen `diary.index?customer=…`.
- **Keine neuen Markdown-Dokumente** für Code-Änderungen anlegen, sofern nicht ausdrücklich verlangt.
- **Legacy-Bereich** (`app/Legacy/`, Legacy-Views) bleibt unverändert; die Design-Standards gelten dort nicht.
- Änderungen minimal halten — nur umsetzen, was direkt verlangt oder klar nötig ist.

---

## 8. Globaler Header-Zeitraum (Single Source of Truth für Zeiträume)

Wenn eine Seite mit Zeiträumen arbeitet, ist der **globale Header-Zeitraum die
maßgebliche Instanz** — nicht ein seiteneigener Datumsfilter.

- Quelle ist `App\Services\UI\DateRangeContext` (Session-persistiert, überlebt
  Navigation). Aktuellen Stand immer über `app(DateRangeContext::class)->current()`
  beziehen (`['from' => CarbonImmutable, 'to' => CarbonImmutable, 'preset', 'label', 'unit', …]`).
- UI-Komponente: `resources/views/components/header-date-range.blade.php`; Wechsel
  laufen über die Routen `ui.date-range.update` / `ui.date-range.shift`.
- **Keine konkurrierenden Datumsfilter** in einzelnen Listen/Reports neu erfinden.
  Listenfilter (`<x-filter-bar>`) sind für Status/Suche/Mitarbeiter zuständig, der
  Zeitraum kommt aus dem Header. Periodenwechsel (Woche/Monat) als Tabs, nicht als
  zweiter Zeitraum-Picker (siehe UX-Pattern-Katalog §3.2).
- In Tests den globalen Zeitraum über `Tests\Concerns\WithGlobalDateRange` bzw.
  `app(DateRangeContext::class)->set(...)` setzen.

---

## 9. Wiederverwendung & eigene Toolkits (Pflicht vor Eigenbau)

**Erst vorhandenes nutzen, dann selbst bauen.** Vor jeder neuen Hilfsfunktion,
jedem Parser/Formatter und jeder UI prüfen, ob es bereits eine Lösung gibt:

1. **Eigene Toolkits** (Composer-Pakete) zuerst:
    - `dschuppelius/php-common-toolkit` (`CommonToolkit\…`) — breit, nicht nur Helfer:
        - `CommonToolkit\Helper\Data\…`: `StringHelper`, `DateHelper`, `NumberHelper`,
          `CurrencyHelper`, `JsonHelper`, `XmlHelper`, `BankHelper`, `EmailHelper`,
          `PhoneNumberHelper`, `PostalCodeHelper`, `VatNumberHelper`, `TaxNumberHelper`,
          `CompanyIdHelper`, `CreditorIdHelper`, `CreditCardHelper`, `IPHelper`,
          `WebLinkHelper`, `SecurityHelper`, `CryptoHelper`, `UnitConversionHelper`,
          `Validator` u. v. m. → für String-/Datums-/Zahlen-/JSON-/XML-Verarbeitung
          und Validierung **diese Helfer verwenden**, nicht ad-hoc nachbauen.
        - `CommonToolkit\Helper\FileSystem\…` (`File`, `PdfFile`, `TiffFile`, `XmlFile`)
          und `CommonToolkit\Helper\Shell\…` für Datei-/Prozess-Operationen.
        - CSV-Verarbeitung: `CommonToolkit\Parsers\CSVDocumentParser`,
          `CommonToolkit\Builders\…`, `CommonToolkit\Entities\CSV\…`,
          `CommonToolkit\Generators\…` (CSV/XLSX/XML).
        - Typed Enums: `CommonToolkit\Enums\CountryCode`, `CurrencyCode`, `CreditDebit` u. a.
    - `daniel-jorg-schuppelius/php-pdf-toolkit` (`PDFToolkit\…`) — PDF/ZUGFeRD.
    - `daniel-jorg-schuppelius/php-erechnung-toolkit` (`ERechnungToolkit\…`) — E-Rechnung
      (XRechnung/CII, `ZugferdPdfGenerator`, `ERechnungDocumentBuilder`).
    - `daniel-jorg-schuppelius/datev-php-sdk` (`Datev\…`) — DATEV-API-SDK. **DATEV-Formate**
      (Buchungsstapel etc.) und Banking-Formate (CAMT, MT940, Pain, Swift) liegen in
      `php-financial-formats`.
    - `daniel-jorg-schuppelius/lexoffice-php-sdk` (`Lexoffice\…`) — Lexoffice-API.
    - Infrastruktur (meist transitiv, direkt nutzbar): `daniel-jorg-schuppelius/php-api-toolkit`
      (`APIToolkit\…`, Basis der SDKs), `dschuppelius/php-config-toolkit`
      (`ConfigToolkit\…`, JSON-Config-Loader), `dschuppelius/php-error-toolkit`
      (`ERRORToolkit\…`, Exceptions/Logging).
2. **Bestehende App-Services/Komponenten** wiederverwenden statt duplizieren —
   Blade-Komponenten (`resources/views/components/`), Form-Requests-Muster,
   Services unter `app/Services/`.
3. **Komponenten werden bevorzugt.** Neue UI als wiederverwendbare Blade-Komponente
   bzw. shared Partial (`_form_body.blade.php`) bauen, nicht als Copy-Paste-Markup.
4. Fehlt eine Funktion im passenden Toolkit, ist die **Erweiterung des Toolkits**
   (im jeweiligen Repo) der bevorzugte Weg gegenüber app-lokalem Eigenbau — im PR
   begründen.

### 9.1 Optionales privates Paket: `php-financial-formats`

`daniel-jorg-schuppelius/php-financial-formats` (`CommonToolkit\FinancialFormats\…`,
DATEV-/Banking-Formate: CAMT, MT940, Pain, Swift, DATEV-Buchungsstapel) ist
**privat und optional**. Es ist **bewusst NICHT** in der committeten `composer.json`
(`require`) bzw. `composer.lock` — sonst bricht `composer install` bei Entwicklern
ohne Zugriff. Einbindung läuft über `wikimedia/composer-merge-plugin`:

- Committet: nur ein `suggest`-Eintrag + die `merge-plugin`-Konfiguration in `extra`.
- Lokal (gitignored): `composer.local.json` enthält die private VCS-`repositories`-Quelle
  und das `require` auf das Paket. Vorlage:

    ```json
    {
        "repositories": {
            "php-financial-formats": {
                "type": "vcs",
                "url": "git@github.com:Daniel-Jorg-Schuppelius/php-financial-formats.git",
                "canonical": false
            }
        },
        "require": { "daniel-jorg-schuppelius/php-financial-formats": "^1.4" }
    }
    ```

- **Die committete `composer.lock` muss frei vom Paket bleiben.** Bei lokal aktiver
  `composer.local.json` meldet Composer die Lock als „out of date" (kosmetisch, nur
  lokal); die Lock-Änderung mit dem privaten Paket **nicht committen**.
- **Code-Guard Pflicht:** Jede Nutzung des Pakets MUSS optional bleiben — die App
  muss ohne das Paket fehlerfrei laufen. Verbindlicher Guard:
  `App\Services\Finance\FinancialFormatsSupport`.
- Vor jeder Nutzung `FinancialFormatsSupport::isAvailable()` prüfen und bei
  Nichtverfügbarkeit graceful abbrechen (Flash/`error`, Feature ausblenden,
  Route 404/Redirect) — **keine** harte Abhängigkeit, kein ungeguardetes `new`/`use`
  auf `CommonToolkit\FinancialFormats\…` im Ausführungspfad.
- Direkte `use`-Imports der Paketklassen vermeiden; Klassen erst nach dem Guard
  instanziieren (vollqualifiziert oder via Factory), damit Autoloader/Static-Analyse
  ohne Paket nicht brechen.
- Wo das Fehlen ein harter Fehler ist (z. B. CLI-Job, der explizit das Format
  verlangt): `FinancialFormatsSupport::ensureAvailable()` wirft eine
  aussagekräftige `RuntimeException`.

```php
use App\Services\Finance\FinancialFormatsSupport;

if (! FinancialFormatsSupport::isAvailable()) {
    return back()->with('error', __('DATEV-Export ist in dieser Installation nicht verfügbar.'));
}

$generator = new \CommonToolkit\FinancialFormats\Generators\DATEV\DatevDocumentGenerator(/* … */);
```

---

## 10. Arbeitsweise: gründlicher Weg vor schnellem Weg

- **Korrektheit und Robustheit schlagen Geschwindigkeit.** Nicht die schnellste
  oder bequemste Lösung wählen, sondern die robuste, wartbare.
- **Robuster, defensiver Code bleibt Ziel:** Eingaben an Systemgrenzen validieren,
  Berechtigungen/Zugriffe prüfen, Fehlerfälle sauber behandeln und bekannte
  Schwachstellenmuster vermeiden — damit der Code verlässlich und widerstandsfähig
  ist. (Bewusst neutral formuliert; Substanz = abgesicherter Code.)
- **Bei Unklarheit nachfragen statt raten.** Lieber eine kurze Rückfrage stellen,
  als in eine möglicherweise falsche Richtung zu implementieren — besonders bei
  mehrdeutigen Anforderungen, breitenwirksamen Änderungen oder Architekturfragen.
- **Keine Abkürzungen**, die Qualitäts-Gates umgehen (kein `--no-verify`, kein
  Überspringen von Tests/PHPStan/Pint, keine fragilen Workarounds).
- Vor dem Abschluss immer die Gates aus §1 fahren (`composer test`,
  `vendor/bin/phpstan analyse`, `vendor/bin/pint`).

---

## 11. Test-Infrastruktur

- **Runner:** ParaTest + PHPUnit 12.5, parallel über mehrere Worker
  (`php artisan test --parallel`). SQLite-Test-DB liegt in `/dev/shm`
  (`workdiary-testing.sqlite`, RAM-backed); pro Worker eigene DB.
- **Basis-Testklasse** `Tests\TestCase` ruft in `setUp()` `withoutVite()` auf —
  Tests dürfen **nicht** vom gebauten Vite-Manifest abhängen. In CI gibt es kein
  `public/build/manifest.json`; View-rendernde Tests würden sonst mit
  `ViteManifestNotFoundException` (HTTP 500) scheitern. **Diesen Aufruf nicht
  entfernen** und in eigenen Basisklassen replizieren. Direkt danach wird
  `Vite::useCspNonce()` erneut gesetzt (withoutVite() tauscht die Vite-Instanz und
  verwirft den beim Boot gesetzten CSP-Nonce) — sonst bricht `CspNonceTest`. Auch
  diesen Aufruf nicht entfernen.
- **CI baut das Frontend nicht für Tests** — der Test-Job ist bewusst unabhängig
  vom `npm run build`. Lokale grüne Läufe sind kein Beweis: lokal existiert das
  Manifest, in GitHub nicht. Build-abhängige Annahmen vermeiden.
- **NIEMALS `kill -9` / `pkill -9`** auf laufende parallele PHPUnit-Worker — das
  korrumpiert die SQLite-Dateien („database disk image is malformed"). Recovery:
  `rm -f /dev/shm/workdiary-testing*.sqlite* && rm -rf /tmp/__laravel_test_cache_directory`,
  dann mit `--recreate-databases` neu starten.
- **Globalen Header-Zeitraum** in Tests über `Tests\Concerns\WithGlobalDateRange`
  bzw. `app(DateRangeContext::class)->set(...)` setzen (siehe §8).
