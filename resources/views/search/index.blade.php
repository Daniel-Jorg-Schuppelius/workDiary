{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Vollergebnisseite der globalen Suche (globale-suche.md AK 2–3; Vollaudit
     2026-07, M8): gruppierte Treffer aus denselben Queries wie die
     Command-Palette, mit Filtern Domäne/Zeitraum/Person/Kunde. --}}

@extends('layouts.app')

@section('title', __('Suche'))
@section('nav-title', __('Suche'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Alle Treffer der globalen Suche — gefiltert nach Domäne, Zeitraum, Person und Kunde.')">
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('search.index')" :reset="route('search.index')">
        <input type="search" name="q" value="{{ $q }}" required minlength="2" maxlength="120"
               placeholder="{{ __('Suchbegriff…') }}"
               class="input input-sm input-bordered w-56 shrink-0" aria-label="{{ __('Suchbegriff') }}">
        <x-filter-field :label="__('Domäne')" for="search-domain">
            <select id="search-domain" name="domain" class="select select-sm select-bordered w-44 shrink-0">
                <option value="">{{ __('alle') }}</option>
                @foreach ($domains as $key => $label)
                    <option value="{{ $key }}" @selected($selectedDomain === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      from-id="search-from" to-id="search-to" :from="$from" :to="$to" />
        @if ($selectablePersons !== null)
            <x-filter-field :label="__('Person')" for="search-person">
                <select id="search-person" name="person" class="select select-sm select-bordered w-40 shrink-0">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($selectablePersons as $p)
                        <option value="{{ $p->sqid }}" @selected($selectedPersonId === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
        <x-filter-field :label="__('Kunde')" for="search-customer">
            <select id="search-customer" name="customer" class="select select-sm select-bordered w-44 shrink-0">
                <option value="">{{ __('alle') }}</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->sqid }}" @selected($selectedCustomerId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($q === '' || mb_strlen($q) < 2)
        <x-empty-state icon="search" :title="__('Tippe mindestens 2 Zeichen, um Ergebnisse zu sehen.')" compact />
    @elseif ($groups === [])
        <x-empty-state icon="search_off" :title="__('Keine Treffer.')" compact />
    @else
        <div class="text-sm text-base-content/70">
            {{ trans_choice(':count Treffer|:count Treffer', $total, ['count' => $total]) }}
        </div>

        @foreach ($groups as $group)
            <x-card>
                <x-slot:title>
                    <span class="flex items-center gap-2">
                        <x-icon name="{{ $group['icon'] }}" class="text-base" />
                        {{ $group['label'] }}
                        <span class="badge badge-sm">{{ count($group['items']) }}</span>
                    </span>
                </x-slot:title>
                <ul class="divide-y divide-base-200">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="flex items-start gap-3 rounded-box px-2 py-2 hover:bg-base-200">
                                <x-icon name="{{ $group['icon'] }}" class="text-base text-muted" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $item['title'] }}</span>
                                    @if ($item['subtitle'])
                                        <span class="block truncate text-xs text-muted">{{ $item['subtitle'] }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($selectedDomain === null && count($group['items']) >= 25)
                    <a class="link link-hover mt-2 block text-sm"
                       href="{{ route('search.index', array_filter(['q' => $q, 'domain' => $group['key'], 'from' => $from, 'to' => $to])) }}">
                        {{ __('Alle Treffer dieser Domäne →') }}
                    </a>
                @endif
            </x-card>
        @endforeach
    @endif
</x-page-shell>
@endsection
