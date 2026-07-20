@extends('layouts.app')

@section('title', __('Leitstellen-Karte'))
@section('nav-title', __('Leitstellen-Karte'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-page-shell class="overflow-auto lg:overflow-clip">
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Aufträge des Zeitraums auf einer Karte — SLA-Risiko hervorgehoben.')">
                <x-slot:actions>
                    <x-icon-btn icon="view_column" tone="ghost" size="sm"
                                :href="route('dispatch.board', request()->only(['from', 'to', 'user']))"
                                show-label>{{ __('Board') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-filter-bar :action="route('dispatch.map')" :reset="route('dispatch.map')">
            <x-filter-field :label="__('Nur SLA-Risiko')" for="map-risk">
                <input id="map-risk" type="checkbox" name="risk" value="1"
                       class="toggle toggle-sm toggle-error" @checked($onlyRisk) data-autosubmit="request" />
            </x-filter-field>
            <x-filter-field :label="__('Nur unbestätigte')" for="map-unconfirmed">
                <input id="map-unconfirmed" type="checkbox" name="unconfirmed" value="1"
                       class="toggle toggle-sm" @checked($onlyUnconfirmed) data-autosubmit="request" />
            </x-filter-field>
            <x-filter-field :label="__('Priorität')" for="map-priority">
                <select id="map-priority" name="priority" class="select select-sm select-bordered w-36 shrink-0" data-autosubmit="request">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($priorityOptions as $option)
                        <option value="{{ $option->value }}" @selected($selectedPriority === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($selectableUsers !== null)
                <x-filter-field :label="__('Mitarbeiter')" for="map-user">
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
                <span>{{ trans_choice(':count Marker|:count Marker', $markerCount, ['count' => $markerCount]) }}</span>
                <span class="text-xs">{{ __('Rot = SLA-Risiko · Layer rechts oben ein-/ausblenden.') }}</span>
            </div>
            <x-map :markers="$markers" :layers="$layers" height="100%" :zoom="9" class="min-h-0 flex-1" />
        </x-card>
    </x-page-shell>
@endsection
