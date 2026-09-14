{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entities.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Stammdaten & Objekte der globalen Suche (Kunden, Projekte, Assets, …).
     Erwartet: $groups, $selectedDomain, $criteria. --}}
@if ($groups !== [])
    <section class="space-y-3" aria-labelledby="search-entities-title">
        <h2 id="search-entities-title" class="text-sm font-semibold uppercase tracking-wider text-muted">{{ __('search.entities.title') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
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
                    @if ($selectedDomain === null && count($group['items']) >= 10)
                        <a class="link link-hover mt-2 block text-sm"
                           href="{{ route('search.index', $criteria->toParameters(['domain' => $group['key']])) }}">
                            {{ __('search.entities.more') }}
                        </a>
                    @endif
                </x-card>
            @endforeach
        </div>
    </section>
@elseif ($selectedDomain !== null)
    <x-empty-state framed icon="search_off" :title="__('search.empty.none')" />
@endif
