@extends('layouts.app')

@section('title', __('Tourenkarte'))
@section('nav-title', __('Tourenkarte'))

@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-page-shell class="overflow-auto lg:overflow-clip">
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Touren, offene Aufträge & Stammdaten des Zeitraums auf einer Karte.')">
                <x-slot:actions>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('tours.create')"
                                show-label>{{ __('Neue Tour') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @include('tours._view-tabs')

        <x-filter-bar :action="route('tours.map')" :reset="route('tours.map')">
            <x-filter-field :label="__('Status')" for="map-status">
                <select id="map-status" name="status" class="select select-sm select-bordered w-32 shrink-0">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($selectableUsers !== null)
                <x-filter-field :label="__('Fahrer')" for="map-user">
                    <select id="map-user" name="user" class="select select-sm select-bordered w-40 shrink-0">
                        <option value="all">{{ __('alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->sqid }}" @selected($targetUser?->sqid === $u->sqid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
        </x-filter-bar>

        <x-card padding="p-0" class="flex h-[60dvh] flex-col lg:h-auto lg:min-h-0 lg:flex-1">
            <div class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2 text-sm text-base-content/70">
                <span>{{ trans_choice(':count Tour|:count Touren', $tourCount, ['count' => $tourCount]) }}</span>
                <span class="text-xs">{{ __('Layer rechts oben ein-/ausblenden.') }}</span>
            </div>
            <x-map :markers="$markers" :routes="$routes" :layers="$layers" height="100%" :zoom="9" class="min-h-0 flex-1" />
        </x-card>
    </x-page-shell>
@endsection
