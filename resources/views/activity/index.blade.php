{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Aktivitäts-Feed der Organisation (Feature 183). Variablen: $items (list<TimelineItem>), $hasMore, $page, $types, $users, $filters, $rangeLabel. --}}

@extends('layouts.app')

@section('title', __('activity.title'))
@section('nav-title', __('activity.title'))

@section('content')
<x-index-page :subtitle="__('activity.subtitle', ['range' => $rangeLabel])">
    <x-filter-bar :action="route('activity.index')" :reset="route('activity.index')">
        <select name="type" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('activity.filter.type') }}">
            <option value="">{{ __('activity.filter.all_types') }}</option>
            @foreach ($types as $key => $class)
                <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ __('entity-types.' . class_basename($class)) }}</option>
            @endforeach
        </select>
        <select name="user" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('activity.filter.user') }}">
            <option value="">{{ __('activity.filter.all_users') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected($filters['user'] === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-card>
        @include('timeline._items', ['items' => $items])
    </x-card>

    @if ($page > 1 || $hasMore)
        <div class="flex justify-between gap-2">
            @if ($page > 1)
                <x-button tone="plain" size="sm" :href="route('activity.index', array_filter([...$filters, 'page' => $page - 1]))" icon="chevron_left">{{ __('activity.newer') }}</x-button>
            @else
                <span></span>
            @endif
            @if ($hasMore)
                <x-button tone="plain" size="sm" :href="route('activity.index', array_filter([...$filters, 'page' => $page + 1]))" icon="chevron_right">{{ __('activity.older') }}</x-button>
            @endif
        </div>
    @endif
</x-index-page>
@endsection
