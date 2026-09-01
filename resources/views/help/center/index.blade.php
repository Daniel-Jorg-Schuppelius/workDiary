{{--
  Created on   : Tue Sep 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Hilfecenter-Übersicht (Feature 039, MVP-752): drei Modi über EINE Route —
     Kachelübersicht (Standard), GET-Suche (?q=…) und Bereichsansicht
     (?bereich=…). Ein Inhaltsbestand: alles kommt aus help_topics. --}}

@extends('layouts.app')

@section('title', __('Hilfecenter'))
@section('nav-title', __('Hilfecenter'))

@section('content')
<x-index-page :subtitle="__('Anleitungen, Hintergründe und Prozesse zu allen Funktionen — durchsuchbar und nach Themen geordnet.')">
    <x-slot:actions>
        <x-button tone="outline" size="sm" icon="flag" class="btn-warning"
                  data-entry-modal-trigger
                  :href="route('problem-reports.create', ['route' => 'help.center.index', 'url' => url()->current()])">
            {{ __('errors.report_problem') }}
        </x-button>
    </x-slot:actions>

    {{-- Hero-Suche: sichtbares Label (Platzhalter ist keine Beschriftung). --}}
    <div class="rounded-(--panel-radius) border border-base-300 bg-base-100 p-6 shadow-xs">
        <form method="GET" action="{{ route('help.center.index') }}" role="search"
              class="mx-auto flex w-full max-w-2xl flex-col items-center gap-3">
            <label for="help-center-search" class="font-['Space_Grotesk'] text-lg font-semibold text-base-content">
                {{ __('Wie können wir helfen?') }}
            </label>
            <div class="flex w-full gap-2">
                <label class="input input-bordered flex grow items-center gap-2">
                    <x-icon name="search" class="text-muted" />
                    <input type="search" id="help-center-search" name="q" minlength="2"
                           value="{{ $mode === 'search' ? $query : '' }}"
                           class="grow"
                           placeholder="{{ __('z. B. Rechnung stornieren') }}"
                           aria-label="{{ __('Hilfethemen durchsuchen') }}">
                </label>
                <x-button type="submit" tone="primary">{{ __('Suchen') }}</x-button>
            </div>
            <p class="text-xs text-muted">
                {{ __('Taste F1 öffnet die Kontexthilfe zur aktuellen Seite.') }}
            </p>
        </form>
    </div>

    @if ($mode === 'search')
        {{-- Suchergebnisse: nur sichtbare Topics, Trefferzahl entsprechend. --}}
        @if ($results->total() === 0)
            <x-empty-state framed
                icon="search_off"
                :title="__('Keine passenden Hilfethemen gefunden.')"
                :message="__('Prüfe die Schreibweise oder versuche einen allgemeineren Begriff.')">
                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <x-button tone="outline" size="sm" :href="route('help.center.index')">
                        {{ __('Suche zurücksetzen') }}
                    </x-button>
                    <x-button tone="outline" size="sm" icon="flag" class="btn-warning"
                              data-entry-modal-trigger
                              :href="route('problem-reports.create', ['route' => 'help.center.index', 'url' => url()->full()])">
                        {{ __('errors.report_problem') }}
                    </x-button>
                </div>
            </x-empty-state>
        @else
            <div class="rounded-(--panel-radius) border border-base-300 bg-base-100 shadow-xs">
                <div class="border-b border-base-300 px-5 py-3">
                    <h2 class="font-['Space_Grotesk'] text-sm font-semibold text-base-content">
                        {{ __(':count Treffer für „:query“', ['count' => $results->total(), 'query' => $query]) }}
                    </h2>
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($results as $row)
                        <li>
                            <a href="{{ route('help.center.show', ['topic' => $row->topic]) }}"
                               class="flex items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-base-200">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-base-content">{{ $row->title }}</span>
                                    @php($snippet = $row->search_snippet ?? null)
                                    @if (is_array($snippet) && ($snippet[1] !== '' || $snippet[2] !== ''))
                                        {{-- Segmente einzeln escaped; nur der Treffer bekommt <mark> (MVP-753). --}}
                                        <span class="block truncate text-xs text-muted">{{ $snippet[0] }}@if ($snippet[1] !== '')<mark class="rounded bg-warning/40 px-0.5 text-base-content">{{ $snippet[1] }}</mark>@endif{{ $snippet[2] }}</span>
                                    @endif
                                    <span class="block text-xs text-muted">{{ $sectionTitles[$catalog->sectionKeyFor($row->topic)] ?? '' }}</span>
                                </span>
                                <x-icon name="chevron_right" class="shrink-0 text-primary" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-pagination :paginator="$results" standing />
        @endif
    @elseif ($mode === 'section')
        {{-- Bereichsansicht: alle sichtbaren Artikel eines Themenbereichs. --}}
        <div class="rounded-(--panel-radius) border border-base-300 bg-base-100 shadow-xs">
            <div class="flex items-center justify-between gap-3 border-b border-base-300 px-5 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <x-icon :name="$section['icon']" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="truncate font-['Space_Grotesk'] text-sm font-semibold text-base-content">{{ $section['title'] }}</h2>
                        <p class="truncate text-xs text-muted">{{ $section['description'] }}</p>
                    </div>
                </div>
                <x-button tone="outline" size="sm" icon="arrow_back" :href="route('help.center.index')">
                    {{ __('Zur Übersicht') }}
                </x-button>
            </div>
            <ul class="divide-y divide-base-300">
                @foreach ($results as $row)
                    <li>
                        <a href="{{ route('help.center.show', ['topic' => $row->topic]) }}"
                           class="flex items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-base-200">
                            <span class="truncate font-medium text-base-content">{{ $row->title }}</span>
                            <x-icon name="chevron_right" class="shrink-0 text-primary" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <x-pagination :paginator="$results" standing />
    @else
        {{-- Kachelübersicht: Themenbereiche mit sichtbarer Artikelzahl. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($sections as $section)
                <a href="{{ route('help.center.index', ['bereich' => $section['key']]) }}"
                   class="group relative flex flex-col gap-2 overflow-hidden rounded-(--panel-radius) border border-base-300 bg-base-100 p-4 shadow-xs transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-primary/60">
                    {{-- Dekorative Illustration (MVP-755): das Bereichssymbol als
                         großes Wasserzeichen — theme-fest (currentColor), rein
                         visuell (aria-hidden über x-icon). --}}
                    <span class="pointer-events-none absolute -bottom-4 -right-3 text-primary opacity-[0.07] transition-opacity group-hover:opacity-[0.12]">
                        <x-icon :name="$section['icon']" size="88px" />
                    </span>
                    <span class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <x-icon :name="$section['icon']" />
                        </span>
                        <span class="font-['Space_Grotesk'] text-sm font-semibold text-base-content">{{ $section['title'] }}</span>
                    </span>
                    <span class="text-xs leading-relaxed text-muted">{{ $section['description'] }}</span>
                    <span class="mt-auto flex items-center justify-between pt-1">
                        <span class="badge badge-ghost badge-sm">{{ trans_choice(':count Artikel|:count Artikel', $section['count'], ['count' => $section['count']]) }}</span>
                        <x-icon name="chevron_right" class="text-primary transition-transform group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
        @if (($popular ?? []) !== [])
            {{-- Beliebte Themen (MVP-755): meistgelesene Artikel der eigenen
                 Organisation, bereits sichtbarkeitsgefiltert. --}}
            <div class="rounded-(--panel-radius) border border-base-300 bg-base-100 p-5 shadow-xs">
                <h2 class="mb-3 font-['Space_Grotesk'] text-sm font-semibold text-base-content">{{ __('Beliebte Themen') }}</h2>
                <div class="grid grid-cols-1 gap-x-5 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($popular as $entry)
                        <a href="{{ route('help.center.show', ['topic' => $entry['topic']]) }}"
                           class="flex items-center gap-2 text-sm">
                            <x-icon name="chevron_right" class="shrink-0 text-muted" size="14px" />
                            <span class="link link-primary truncate">{{ $entry['title'] }}</span>
                            <span class="shrink-0 text-xs text-muted">{{ $entry['section'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
        <p class="text-center text-xs text-muted">
            {{ __(':count Artikel in deiner Sprache verfügbar.', ['count' => $totalCount]) }}
        </p>
    @endif
</x-index-page>
@endsection
