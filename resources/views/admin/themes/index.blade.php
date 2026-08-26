{{--
  Created on   : Sat Jun 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Theme-Editor (Pro+). Listet Built-in- und eigene Themes, erlaubt das Anlegen/
  Bearbeiten eigener Themes (Modal) und das Setzen des Org-Default-Themes.
--}}
@extends('layouts.app')

@section('title', __('Themes'))
@section('nav-title', __('Themes'))

@section('content')
@php
    /** @var \App\Models\Organization $organization */
    /** @var \App\Services\ThemeService $theme */
    $builtin = $theme->builtinThemes();
    $lightThemes = array_values(array_filter($builtin, fn($t) => $t['scheme'] === 'light'));
    $darkThemes = array_values(array_filter($builtin, fn($t) => $t['scheme'] === 'dark'));
    $custom = $theme->customDefinitions();
    $defaultLight = $theme->organizationDefaultLight();
    $defaultDark = $theme->organizationDefaultDark();
    $maxCustom = (int) config('theme.max_custom', 12);
@endphp

<x-page-shell>
    {{-- Flash-Meldungen (success/error) rendert bereits das App-Layout global;
         hier bewusst NICHT erneut ausgeben (sonst doppelte Meldung). --}}

    {{-- ── Org-Default ───────────────────────────────────────────────── --}}
    <div class="card wd-surface">
        <div class="card-body">
            <h2 class="card-title"><x-icon name="star" /> {{ __('Standard-Theme der Organisation') }}</h2>
            <p class="text-sm opacity-70">
                {{ __('Gilt für alle Mitglieder, die keine eigene Theme-Wahl im Profil getroffen haben. Der Automatik-Modus wechselt je nach System-Einstellung zwischen dem Hell- und dem Dunkel-Theme.') }}
            </p>
            @php $customLight = array_filter($custom, fn($d) => $d->scheme === 'light'); $customDark = array_filter($custom, fn($d) => $d->scheme === 'dark'); @endphp
            <form method="POST" action="{{ route('admin.themes.default') }}" class="flex flex-wrap items-end gap-3 mt-2">
                @csrf
                @method('PUT')
                <div class="fieldset grow max-w-xs">
                    <label class="fieldset-label" for="default-light"><x-icon name="light_mode" class="text-base" /> {{ __('Hell-Modus') }}</label>
                    <select id="default-light" name="default_light" class="select select-bordered select-sm w-full">
                        @foreach ($lightThemes as $t)
                            <option value="{{ $t['key'] }}" @selected($defaultLight === $t['key'])>{{ $t['label'] }}</option>
                        @endforeach
                        @if ($customLight !== [])
                            <optgroup label="{{ __('Eigene Themes') }}">
                                @foreach ($customLight as $d)
                                    <option value="{{ $d->token() }}" @selected($defaultLight === $d->token())>{{ $d->label }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div class="fieldset grow max-w-xs">
                    <label class="fieldset-label" for="default-dark"><x-icon name="dark_mode" class="text-base" /> {{ __('Dunkel-Modus') }}</label>
                    <select id="default-dark" name="default_dark" class="select select-bordered select-sm w-full">
                        @foreach ($darkThemes as $t)
                            <option value="{{ $t['key'] }}" @selected($defaultDark === $t['key'])>{{ $t['label'] }}</option>
                        @endforeach
                        @if ($customDark !== [])
                            <optgroup label="{{ __('Eigene Themes') }}">
                                @foreach ($customDark as $d)
                                    <option value="{{ $d->token() }}" @selected($defaultDark === $d->token())>{{ $d->label }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                {{-- Unsichtbarer Label-Platzhalter, damit der Button strukturgleich
                     zu den Select-Spalten ist (Label + Control) und sauber mit den
                     Dropdowns fluchtet statt leicht tiefer zu sitzen. --}}
                <div class="fieldset">
                    {{-- Leerer (unsichtbarer) Label-Platzhalter für die Höhe der
                         Label-Zeile. KEIN Material-Symbol darin — dessen
                         fonts-loaded-Regel würde `invisible` überstimmen und das
                         Icon sichtbar machen. --}}
                    <span class="fieldset-label invisible select-none" aria-hidden="true">&nbsp;</span>
                    <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('Übernehmen') }}</x-icon-btn>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Eigene Themes ─────────────────────────────────────────────── --}}
    <div class="card wd-surface mt-4">
        <div class="card-body">
            <div class="flex items-center justify-between gap-2">
                <h2 class="card-title"><x-icon name="format_paint" /> {{ __('Eigene Themes') }}</h2>
                @if (count($custom) < $maxCustom)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('admin.themes.create').'?dialog=1'"
                                show-label>{{ __('Neues Theme') }}</x-icon-btn>
                @else
                    <span class="text-xs opacity-60">{{ __('Maximum erreicht (:max).', ['max' => $maxCustom]) }}</span>
                @endif
            </div>

            @if ($custom === [])
                <p class="text-sm opacity-60 mt-2">{{ __('Noch keine eigenen Themes. Lege eines an, um die Farbpalette deiner Organisation abzubilden.') }}</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-2">
                    @foreach ($custom as $d)
                        @php $vars = $d->toCssVars(); @endphp
                        <div class="rounded-box border border-base-300 overflow-hidden">
                            <div class="p-3" style="background:{{ $vars['--color-base-100'] }};color:{{ $vars['--color-base-content'] }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">{{ $d->label }}</span>
                                    <span class="text-[0.65rem] uppercase tracking-wide opacity-70">{{ $d->scheme === 'dark' ? __('Dunkel') : __('Hell') }}</span>
                                </div>
                                <div class="flex gap-1 mt-2">
                                    @foreach (['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error'] as $c)
                                        <span class="inline-block w-5 h-5 rounded" style="background:{{ $vars['--color-' . $c] }}" title="{{ $c }}"></span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 px-3 py-2 bg-base-100">
                                <code class="text-xs opacity-60">{{ $d->token() }}</code>
                                <div class="flex items-center gap-1">
                                    @if ($defaultLight === $d->token())
                                        <x-status-badge tone="primary" size="sm">{{ __('Standard hell') }}</x-status-badge>
                                    @elseif ($defaultDark === $d->token())
                                        <x-status-badge tone="primary" size="sm">{{ __('Standard dunkel') }}</x-status-badge>
                                    @endif
                                    <x-icon-btn icon="edit" tone="ghost" size="sm"
                                                data-entry-modal-trigger
                                                :href="route('admin.themes.edit', $d->key).'?dialog=1'"
                                                :label="__('Bearbeiten')" />
                                    <x-action-form :action="route('admin.themes.destroy', $d->key)"
                                          method="DELETE"
                                          :confirm="__('Theme wirklich löschen?')"
                                          :confirm-label="__('Löschen')">
                                        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('Löschen')" />
                                    </x-action-form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Vordefinierte Themes (Übersicht) ──────────────────────────── --}}
    <div class="card wd-surface mt-4">
        <div class="card-body">
            <h2 class="card-title"><x-icon name="palette" /> {{ __('Vordefinierte Themes') }}</h2>
            <p class="text-sm opacity-70">{{ __('Stehen allen Mitgliedern zur Auswahl und können als Standard gesetzt werden.') }}</p>
            <div class="flex flex-wrap gap-2 mt-2">
                @foreach ($builtin as $t)
                    <span class="badge badge-outline gap-1" data-theme="{{ $t['key'] }}">
                        <span class="inline-block w-3 h-3 rounded-full" style="background:var(--color-primary)"></span>
                        {{ $t['label'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</x-page-shell>
@endsection
