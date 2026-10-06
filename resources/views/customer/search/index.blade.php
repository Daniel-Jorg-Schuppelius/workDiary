{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Suche im Kundenportal (MVP-1019). Variablen: $q, $groups (Bereich → Treffer)
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-1">{{ __('customer_search.title') }}</h1>
    <p class="text-sm text-muted mb-4">{{ __('customer_search.subtitle') }}</p>

    <form method="GET" action="{{ route('customer.search') }}" class="mb-6 flex gap-2" role="search">
        <label for="portal-search" class="sr-only">{{ __('customer_search.field') }}</label>
        <input id="portal-search" type="search" name="q" value="{{ $q }}" minlength="2" maxlength="100" required
               class="input input-bordered w-full max-w-md" placeholder="{{ __('customer_search.placeholder') }}">
        <x-button type="submit" tone="primary" icon="search"><span>{{ __('customer_search.submit') }}</span></x-button>
    </form>

    @if ($q !== '' && mb_strlen($q) >= 2)
        @if (collect($groups)->flatten(1)->isEmpty())
            <x-empty-state icon="search_off" :title="__('customer_search.empty', ['q' => $q])" compact />
        @endif
        @foreach ($groups as $group => $hits)
            @continue($hits === [])
            <section class="mb-6">
                <h2 class="mb-2 text-lg font-semibold">{{ __('customer_search.group.' . $group) }}</h2>
                <ul class="divide-y divide-base-300 rounded-box border border-base-300">
                    @foreach ($hits as $hit)
                        <li class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                            <a href="{{ $hit['url'] }}" class="link link-hover">{{ $hit['label'] }}</a>
                            <span class="text-xs text-muted">{{ $hit['meta'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
@endsection
