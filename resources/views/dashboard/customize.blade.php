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
                            <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $preset['label'] }}</p>
                                    <p class="text-xs text-muted">{{ $preset['description'] }}</p>
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
                        <li class="flex flex-wrap items-center gap-2 sm:flex-nowrap" data-tab-row data-tab-key="{{ $tab['key'] }}">
                            <x-icon :name="$tab['icon'] ?: 'tab'" class="text-muted" data-tab-icon-preview />
                            <input type="text"
                                   name="tabs[{{ $idx }}][label]"
                                   value="{{ $tab['label'] }}"
                                   maxlength="40"
                                   class="input input-bordered input-sm min-w-40 flex-1"
                                   aria-label="{{ __('Bezeichnung des Bereichs') }}"
                                   data-default-label="{{ __('Bereich') }}"
                                   data-tab-label>
                            <input type="text"
                                   name="tabs[{{ $idx }}][icon]"
                                   value="{{ $tab['icon'] }}"
                                   maxlength="40"
                                   class="input input-bordered input-sm w-40 font-mono"
                                   list="dashboard-tab-icons"
                                   placeholder="tab"
                                   aria-label="{{ __('Symbol des Bereichs') }}"
                                   data-tab-icon>
                            <input type="hidden" name="tabs[{{ $idx }}][key]" value="{{ $tab['key'] }}" data-tab-key-input>
                            <x-icon-btn type="button" tone="ghost" size="xs" icon="delete" :label="__('Bereich entfernen')" class="text-error" data-tab-remove />
                        </li>
                    @endforeach
                </ul>

                <p class="mt-2 text-xs text-muted" data-tab-empty @if ($tabs !== []) hidden @endif>
                    {{ __('Keine Bereiche angelegt — alle Kacheln liegen auf einer Fläche.') }}
                </p>

                {{-- Vorschläge, keine Beschränkung: jedes Material-Symbol ist erlaubt. --}}
                <datalist id="dashboard-tab-icons">
                    @foreach (['dashboard', 'checklist', 'forum', 'payments', 'schedule', 'event_upcoming', 'group', 'insights', 'inventory_2', 'build', 'health_and_safety', 'folder', 'star', 'flag', 'today'] as $suggestion)
                        <option value="{{ $suggestion }}"></option>
                    @endforeach
                </datalist>

                {{-- Vorlage für neue Bereichszeilen; das Skript klont sie. --}}
                <template data-tab-template>
                    <li class="flex flex-wrap items-center gap-2 sm:flex-nowrap" data-tab-row data-tab-key="">
                        <x-icon name="tab" class="text-muted" data-tab-icon-preview />
                        <input type="text" value="" maxlength="40"
                               class="input input-bordered input-sm min-w-40 flex-1"
                               aria-label="{{ __('Bezeichnung des Bereichs') }}"
                               data-default-label="{{ __('Bereich') }}"
                               data-tab-label>
                        <input type="text" value="" maxlength="40"
                               class="input input-bordered input-sm w-40 font-mono"
                               list="dashboard-tab-icons"
                               placeholder="tab"
                               aria-label="{{ __('Symbol des Bereichs') }}"
                               data-tab-icon>
                        <input type="hidden" value="" data-tab-key-input>
                        <x-icon-btn type="button" tone="ghost" size="xs" icon="delete"
                                    :label="__('Bereich entfernen')" class="text-error" data-tab-remove />
                    </li>
                </template>
            </x-card>

            {{-- ── Kacheln ─────────────────────────────────────────────── --}}
            <x-card padding="p-0">
                <ul id="dashboard-widget-list" class="divide-y divide-base-300" data-widget-list>
                    @foreach ($items as $idx => $item)
                        <li class="flex flex-wrap items-center gap-3 px-3 py-2 sm:flex-nowrap"
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

                            <label class="flex items-center gap-2" data-widget-tab-wrap @if ($tabs === []) hidden @endif>
                                <span class="sr-only">{{ __('Bereich') }}</span>
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
                                <span class="sr-only">{{ __('Breite') }}</span>
                                <select class="select select-bordered select-xs w-28" data-widget-width
                                        name="widgets[{{ $idx }}][width]"
                                        aria-label="{{ __('Breite') }} — {{ $item['label'] }}">
                                    @foreach (\App\Enums\Dashboard\WidgetWidth::cases() as $width)
                                        <option value="{{ $width->value }}" @selected($item['width'] === $width)>{{ $width->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="label cursor-pointer gap-2">
                                <span class="label-text text-xs">{{ __('Sichtbar') }}</span>
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
