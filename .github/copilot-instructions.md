# Copilot / Agent Instructions — workDiary

Diese Datei ist die normative Kurzreferenz für alle automatisierten Code-Änderungen in diesem Repo. Detaillierte Konventionen liegen in `/memories/repo/workdiary-ui-conventions.md`.

## Stack-Grundlagen

- Laravel 13 / PHP 8.4 / CarbonImmutable.
- Frontend: Tailwind v3 + DaisyUI v4 + Material Symbols Outlined (Font).
- Autorisierung: Spatie Permissions.
- Tests: `composer test`; Statisch: `vendor/bin/phpstan analyse`; Linter: `vendor/bin/pint`.

## Index-/Listenseiten-Standard (Corporate Design)

Jede neue Index-/Listenseite (`resources/views/**/index.blade.php`) MUSS dem folgenden Skeleton folgen.

### Skelett ohne Filter

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

    @if ($customers->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' />
    @else
        <x-table>…</x-table>
    @endif
</x-index-page>
@endsection
```

### Skelett mit Filter/Suche

```blade
<x-index-page :subtitle="__('…')">
    <x-slot:actions>…</x-slot:actions>

    <x-filter-bar :action="route('customers.index')" :reset="route('customers.index')">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <select name="status" class="select select-sm select-bordered w-32 shrink-0">…</select>
        …
    </x-filter-bar>

    @if ($customers->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' />
    @else
        <x-table>
            <x-slot:head>…</x-slot:head>
            @foreach ($customers as $c)
                <tr>…</tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
```

### Verbindliche Regeln

1. **Header**: `x-index-page` umschließt alles. KEIN `title`-Prop, KEIN zusätzliches `<h2>` im Body. Titel kommt aus `@section('nav-title')`.
2. **Subtitle Pflicht**: kurze Beschreibung der Seite — was wird hier verwaltet, in welchem Kontext.
3. **Aktionen**: rechte Toolbar-Aktionen ausschließlich via `<x-slot:actions>` mit `<x-icon-btn>`. Primär-Action ist meist „Anlegen" (`icon="add"`, `tone="primary"`, `size="sm"`, `show-label`).
4. **Filter/Suche**:
   - Wenn vorhanden: `<x-filter-bar>` direkt unter der Toolbar (einzeilig, scrollbar, sm-Größen).
   - Wenn NICHT vorhanden: filter-bar komplett weglassen — keine leere Karte stehen lassen.
   - Inputs/Selects mit `input-sm` / `select-sm` / `shrink-0` und fixer Breite (`w-24`..`w-48`).
5. **Empty-State**:
   - Liste/Cards: `<x-empty-state framed icon="…" />` — Defaults für `title` und `message` greifen.
   - Tabelle: `<x-table.empty :colspan="…" icon="…" />` als letzte Zeile.
   - Nur `icon` (Domain-Kontext) zwingend setzen; `title`/`message` nur bei abweichendem Wording (z. B. „Noch keine Dienstpläne vorhanden").
6. **Größen-Standard**: `input-sm` / `select-sm` / `btn-sm`. **Kein `xs`.**
7. **Material Symbols** nur gültige Namen (z. B. `add`, `groups`, `calendar_month`, `menu_book`, `inbox`). Heroicon-Namen werden als Literal-Text gerendert.

### Komponenten-Inventar (Quellen)

- `resources/views/components/index-page.blade.php` — Skeleton-Wrapper.
- `resources/views/components/page-shell.blade.php` — Outer Container.
- `resources/views/components/page-toolbar.blade.php` — Toolbar-Karte.
- `resources/views/components/filter-bar.blade.php` — einzeilige Filterkarte.
- `resources/views/components/empty-state.blade.php` — Empty-State (mit `framed`).
- `resources/views/components/table/empty.blade.php` — Empty-Row für `<x-table>`.

## Allgemeines

- `&` statt `&amp;` in `__()`-Strings.
- Drilldowns auf `customer` nutzen `diary.index?customer=…`.
- Keine neuen Markdown-Dokumente für Code-Änderungen anlegen, sofern nicht ausdrücklich verlangt.
