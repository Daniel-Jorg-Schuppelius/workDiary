{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customize.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Dashboard anpassen": oben die Bereiche (Tabs), darunter eine einzige
  sortierbare Kachel-Liste in Dashboard-Reihenfolge (Ziehen oder Pfeiltasten),
  je Kachel Bereich, Breite und Sichtbarkeit. Die Gruppe steht als Badge in
  der Zeile — nicht als Abschnitt, sonst wäre die Liste nicht mehr die
  Reihenfolge, die das Dashboard zeigt.
  Sortier-, Bereichs- und Sync-Logik: resources/js/dashboard-customize.js
--}}
@extends('layouts.app')
@section('title', __('Dashboard anpassen'))
@section('nav-title', __('Dashboard anpassen'))

@php
    /**
     * Auswahl gängiger Material-Symbole für Bereiche. Zum Anklicken — eine
     * <datalist> zeigt nur Namen und filtert bei exaktem Treffer alles weg,
     * das Feld wirkt dann leer. Freie Eingabe bleibt über das Textfeld möglich.
     */
    $iconChoices = [
        'dashboard', 'checklist', 'forum', 'payments', 'schedule', 'event_upcoming',
        'today', 'calendar_month', 'group', 'person', 'insights', 'monitoring',
        'inventory_2', 'build', 'handyman', 'health_and_safety', 'shield', 'gavel',
        'folder', 'description', 'star', 'flag', 'bolt', 'home_work',
    ];
    /** @var array<int, array{key:string,label:string,icon:string,description:?string,group:\App\Enums\Dashboard\WidgetGroup,hidden:bool,width:\App\Enums\Dashboard\WidgetWidth,tab:?string,source:string}> $items */
    /** @var list<array{key:string,label:string,icon:?string}> $tabs */
    /** @var list<array{key:string,label:string,description:string}> $presets */
    /** @var bool $canManageOrgDefault */
    /** @var bool $hasOrgDefault */
    /** @var bool $hasOwnLayout */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Kacheln ziehen oder mit den Pfeilen sortieren, Breite und Bereich wählen, ein- oder ausblenden.')">
                <x-slot:actions>
                    @if ($hasOwnLayout)
                        <form method="POST" action="{{ route('dashboard.customize.reset') }}" class="leading-none">
                            @csrf
                            <x-button type="submit" tone="ghost" size="sm" icon="restart_alt">{{ __('Auf Vorgabe zurücksetzen') }}</x-button>
                        </form>
                    @endif
                    <x-button href="{{ route('dashboard') }}" tone="ghost" size="sm" icon="arrow_back">{{ __('Zurück zum Dashboard') }}</x-button>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($hasOrgDefault && ! $hasOwnLayout)
            <div class="alert alert-info">
                <x-icon name="corporate_fare" />
                <span>{{ __('Es gilt derzeit die Dashboard-Vorgabe der Organisation.') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.customize.save') }}" id="dashboard-customize-form"
              class="flex flex-col gap-4" data-dashboard-customize>
            @csrf

            {{-- ── Fertige Anordnungen ─────────────────────────────────── --}}
            @if ($presets !== [])
                <x-card :title="__('Fertige Anordnungen')" icon="auto_awesome_mosaic">
                    <div class="flex flex-wrap items-center gap-3">
                        @foreach ($presets as $preset)
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-4 py-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-box border border-base-300 bg-base-100 text-base-content/70">
                                        <x-icon name="dashboard_customize" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $preset['label'] }}</p>
                                        <p class="text-xs text-muted">{{ $preset['description'] }}</p>
                                    </div>
                                </div>
                                {{-- Eigenes Formular: das Übernehmen ersetzt die Anordnung
                                     sofort und darf nicht mit dem Speichern-Formular
                                     verschachtelt werden. --}}
                                <x-button type="submit" tone="ghost" size="xs" icon="download"
                                          form="dashboard-preset-{{ $preset['key'] }}">{{ __('Übernehmen') }}</x-button>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- ── Bereiche ────────────────────────────────────────────── --}}
            <x-card :title="__('Bereiche')" icon="tab">
                <x-slot:actions>
                    <x-button type="button" tone="ghost" size="xs" icon="add" data-tab-add>{{ __('Bereich hinzufügen') }}</x-button>
                </x-slot:actions>

                <p class="mb-3 text-xs text-muted">
                    {{ __('Ohne Bereiche liegen alle Kacheln auf einer Fläche. Mit Bereichen erscheint auf dem Dashboard eine Leiste, und jede Kachel gehört zu genau einem davon.') }}
                </p>

                <ul class="space-y-2" data-tab-list>
                    @foreach ($tabs as $idx => $tab)
                        <li class="rounded-box border border-base-300 bg-base-100 px-3 py-2" data-tab-row data-tab-key="{{ $tab['key'] }}">
                            <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-box border border-base-300 bg-base-200 text-base-content/70">
                                    <x-icon :name="$tab['icon'] ?: 'tab'" data-tab-icon-preview />
                                </span>
                                <input type="text"
                                       name="tabs[{{ $idx }}][label]"
                                       value="{{ $tab['label'] }}"
                                       maxlength="40"
                                       class="input input-bordered input-sm min-w-40 flex-1"
                                       placeholder="{{ __('Bezeichnung des Bereichs') }}"
                                       aria-label="{{ __('Bezeichnung des Bereichs') }}"
                                       data-default-label="{{ __('Bereich') }}"
                                       data-tab-label>
                                <input type="text"
                                       name="tabs[{{ $idx }}][icon]"
                                       value="{{ $tab['icon'] }}"
                                       maxlength="40"
                                       class="input input-bordered input-sm w-32 font-mono"
                                       placeholder="tab"
                                       aria-label="{{ __('Symbol des Bereichs') }}"
                                       data-tab-icon>
                                <input type="hidden" name="tabs[{{ $idx }}][key]" value="{{ $tab['key'] }}" data-tab-key-input>

                                {{-- Raster fließt im Dokument (kein absolutes Dropdown): so kann es
                                     weder aus dem Scrollcontainer ausbrechen noch am Panelrand
                                     abgeschnitten werden. --}}
                                <x-icon-btn type="button" tone="ghost" size="xs" icon="delete" :label="__('Bereich entfernen')" class="text-error" data-tab-remove />
                            </div>

                            {{-- Raster als eigener Block unter der Feldzeile: als
                                 Element IN der Zeile würde es sie sprengen, als
                                 absolutes Dropdown aus dem Scrollcontainer ausbrechen. --}}
                            <details class="mt-1">
                                <summary class="btn btn-xs btn-ghost gap-1 [&::-webkit-details-marker]:hidden">
                                    <x-icon name="palette" /> {{ __('Symbol') }}
                                </summary>
                                <div class="mt-2 flex flex-wrap gap-1 rounded-box border border-base-300 bg-base-200 p-2" data-icon-grid>
                                    @foreach ($iconChoices as $choice)
                                        <button type="button"
                                                class="btn btn-xs btn-ghost btn-square"
                                                data-icon-pick="{{ $choice }}"
                                                title="{{ $choice }}"
                                                aria-label="{{ $choice }}">
                                            <x-icon :name="$choice" />
                                        </button>
                                    @endforeach
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-2 text-xs text-muted" data-tab-empty @if ($tabs !== []) hidden @endif>
                    {{ __('Keine Bereiche angelegt — alle Kacheln liegen auf einer Fläche.') }}
                </p>

                {{-- Vorlage für neue Bereichszeilen; das Skript klont sie. --}}
                <template data-tab-template>
                    <li class="rounded-box border border-base-300 bg-base-100 px-3 py-2" data-tab-row data-tab-key="">
                        <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-box border border-base-300 bg-base-200 text-base-content/70">
                                <x-icon name="tab" data-tab-icon-preview />
                            </span>
                            <input type="text" value="" maxlength="40"
                                   class="input input-bordered input-sm min-w-40 flex-1"
                                   placeholder="{{ __('Bezeichnung des Bereichs') }}"
                                   aria-label="{{ __('Bezeichnung des Bereichs') }}"
                                   data-default-label="{{ __('Bereich') }}"
                                   data-tab-label>
                            <input type="text" value="" maxlength="40"
                                   class="input input-bordered input-sm w-32 font-mono"
                                   placeholder="tab"
                                   aria-label="{{ __('Symbol des Bereichs') }}"
                                   data-tab-icon>
                            <input type="hidden" value="" data-tab-key-input>

                            <x-icon-btn type="button" tone="ghost" size="xs" icon="delete"
                                        :label="__('Bereich entfernen')" class="text-error" data-tab-remove />
                        </div>

                        <details class="mt-1">
                            <summary class="btn btn-xs btn-ghost gap-1 [&::-webkit-details-marker]:hidden">
                                <x-icon name="palette" /> {{ __('Symbol') }}
                            </summary>
                            <div class="mt-2 flex flex-wrap gap-1 rounded-box border border-base-300 bg-base-200 p-2" data-icon-grid>
                                @foreach ($iconChoices as $choice)
                                    <button type="button"
                                            class="btn btn-xs btn-ghost btn-square"
                                            data-icon-pick="{{ $choice }}"
                                            title="{{ $choice }}"
                                            aria-label="{{ $choice }}">
                                        <x-icon :name="$choice" />
                                    </button>
                                @endforeach
                            </div>
                        </details>
                    </li>
                </template>
            </x-card>

            {{-- ── Kacheln ─────────────────────────────────────────────── --}}
            <x-card padding="p-0">
                @php $visibleCount = count(array_filter($items, fn (array $i): bool => ! $i['hidden'])); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-3 py-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Kacheln') }}</h2>
                        <span class="badge badge-ghost badge-sm tabular-nums">
                            {{ __(':visible von :total sichtbar', ['visible' => $visibleCount, 'total' => count($items)]) }}
                        </span>
                    </div>
                    {{-- Spaltenköpfe: dieselben Breiten wie in den Zeilen, damit sie
                         darüber stehen. Erst ab lg — darunter bricht die Zeile um. --}}
                    <div class="hidden items-center gap-3 text-xs uppercase tracking-wider text-muted lg:flex">
                        @if ($tabs !== [])
                            <span class="w-36">{{ __('Bereich') }}</span>
                        @endif
                        <span class="w-28">{{ __('Breite') }}</span>
                        <span class="w-24 text-right">{{ __('Sichtbar') }}</span>
                    </div>
                </div>
                <ul id="dashboard-widget-list" class="divide-y divide-base-300" data-widget-list>
                    @foreach ($items as $idx => $item)
                        <li @class([
                                'group flex flex-wrap items-center gap-3 px-3 py-2 transition-colors hover:bg-base-200 sm:flex-nowrap',
                                // Ausgeblendete Kacheln bleiben lesbar, sind aber am
                                // getönten Grund und am Randstreifen sofort erkennbar.
                                'border-l-2 border-l-base-300 bg-base-200/60' => $item['hidden'],
                            ])
                            draggable="true"
                            data-widget-row
                            data-widget-key="{{ $item['key'] }}">
                            <span class="cursor-grab text-muted" data-widget-handle
                                  title="{{ __('Zum Sortieren ziehen') }}" aria-hidden="true">
                                <x-icon name="drag_indicator" />
                            </span>

                            <div class="flex flex-col gap-1">
                                <x-icon-btn type="button" tone="ghost" size="xs" icon="keyboard_arrow_up" :label="__('Nach oben')" class="widget-move-up" />
                                <x-icon-btn type="button" tone="ghost" size="xs" icon="keyboard_arrow_down" :label="__('Nach unten')" class="widget-move-down" />
                            </div>

                            <x-icon name="{{ $item['icon'] }}" class="text-base-content/70" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ $item['label'] }}</span>
                                    <span class="badge badge-ghost badge-sm gap-1">
                                        <x-icon name="{{ $item['group']->icon() }}" class="text-[0.9rem]" />
                                        {{ $item['group']->label() }}
                                    </span>
                                </div>
                                @if ($item['description'])
                                    <p class="text-xs text-muted">{{ $item['description'] }}</p>
                                @endif
                            </div>

                            {{-- Kein <span class="sr-only"> als Beschriftung: das Feld trägt
                                 ein aria-label, und sr-only ist position:absolute — in einer
                                 langen Liste brechen solche Elemente aus dem scrollenden
                                 Container aus und erzeugen einen leeren Fenster-Scrollbalken. --}}
                            <label class="flex items-center gap-2" data-widget-tab-wrap @if ($tabs === []) hidden @endif>
                                <select class="select select-bordered select-xs w-36" data-widget-tab
                                        name="widgets[{{ $idx }}][tab]"
                                        data-always-label="{{ __('Immer sichtbar') }}"
                                        aria-label="{{ __('Bereich') }} — {{ $item['label'] }}">
                                    {{-- Ohne Bereich steht die Kachel über der Leiste und ist
                                         damit in jedem Bereich sichtbar. --}}
                                    <option value="" @selected($item['tab'] === null)>{{ __('Immer sichtbar') }}</option>
                                    @foreach ($tabs as $tab)
                                        <option value="{{ $tab['key'] }}" @selected($item['tab'] === $tab['key'])>{{ $tab['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="flex items-center gap-2">
                                <select class="select select-bordered select-xs w-28" data-widget-width
                                        name="widgets[{{ $idx }}][width]"
                                        aria-label="{{ __('Breite') }} — {{ $item['label'] }}">
                                    @foreach (\App\Enums\Dashboard\WidgetWidth::cases() as $width)
                                        <option value="{{ $width->value }}" @selected($item['width'] === $width)>{{ $width->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="label w-24 cursor-pointer justify-end gap-2">
                                <span class="label-text text-xs lg:hidden">{{ __('Sichtbar') }}</span>
                                <input type="checkbox" class="toggle toggle-primary toggle-sm widget-visible-toggle"
                                       aria-label="{{ __('Sichtbar') }} — {{ $item['label'] }}"
                                       @if (! $item['hidden']) checked @endif>
                            </label>

                            <input type="hidden" name="widgets[{{ $idx }}][key]" value="{{ $item['key'] }}" class="widget-key-input">
                            <input type="hidden" name="widgets[{{ $idx }}][hidden]" value="{{ $item['hidden'] ? '1' : '0' }}" class="widget-hidden-input">
                        </li>
                    @endforeach
                </ul>
            </x-card>

        </form>

        {{-- Preset-Formulare stehen bewusst außerhalb des Speichern-Formulars:
             HTML erlaubt keine verschachtelten <form>-Elemente. --}}
        @foreach ($presets as $preset)
            <form method="POST" action="{{ route('dashboard.customize.preset') }}" id="dashboard-preset-{{ $preset['key'] }}" class="hidden">
                @csrf
                <input type="hidden" name="preset" value="{{ $preset['key'] }}">
            </form>
        @endforeach
    </x-page-shell>

    {{-- Speichern-Balken als STEHENDER Footer (gleiches Muster wie die
         Menü-Anpassung und die Pagination): bei knapp 40 Kacheln wäre ein
         Button am Listenende sonst nur nach vollem Durchscrollen erreichbar.
         Felder und Button liegen außerhalb des <form> und sind über das
         HTML-Attribut form="…" damit verbunden. --}}
    @push('page-footer')
        <div class="shrink-0 mt-(--sidebar-gap) max-md:px-1">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-(--panel-radius) border border-base-300 bg-base-100 px-4 py-2.5 shadow-xs">
                <span class="text-xs text-muted">{{ __('Die Auswahl gilt für dein Konto, auf allen Geräten.') }}</span>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($canManageOrgDefault)
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="scope" value="organization" form="dashboard-customize-form" class="checkbox checkbox-sm">
                            <span class="label-text text-xs">{{ __('Zusätzlich als Standard für die Organisation speichern') }}</span>
                        </label>
                    @endif
                    <x-button type="submit" form="dashboard-customize-form" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
                </div>
            </div>
        </div>
    @endpush
@endsection
