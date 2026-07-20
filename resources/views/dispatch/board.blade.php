@extends('layouts.app')

@section('title', __('Leitstelle') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Leitstelle'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page :subtitle="__('Offene und geplante Aufträge des Zeitraums kompakt nach Disposition.')">
        <x-slot:actions>
        <x-icon-btn icon="auto_fix_high" size="sm" :href="route('dispatch.suggestions')" show-label>{{ __('Leerzeit-Vorschläge') }}</x-icon-btn>
            <x-icon-btn icon="map" tone="ghost" size="sm"
                        :href="route('dispatch.map', request()->only(['from', 'to', 'user']))"
                        show-label>{{ __('Karte') }}</x-icon-btn>
        </x-slot:actions>

        {{-- Ansicht-Umschaltung: Status <-> Mitarbeiter --}}
        @php $baseQuery = request()->only(['from', 'to', 'user', 'status', 'priority']); @endphp
        <x-tab-nav :items="[
            ['route' => 'dispatch.board', 'params' => array_merge($baseQuery, ['group' => 'status']),
             'active' => $groupBy === 'status', 'icon' => 'view_column', 'label' => __('Nach Status')],
            ['route' => 'dispatch.board', 'params' => array_merge($baseQuery, ['group' => 'employee']),
             'active' => $groupBy === 'employee', 'icon' => 'groups', 'label' => __('Nach Mitarbeiter')],
            ['route' => 'dispatch.calendar', 'params' => $baseQuery, 'icon' => 'calendar_month', 'label' => __('Kalender')],
        ]" />

        <x-filter-bar :action="route('dispatch.board')" :reset="route('dispatch.board')">
            <input type="hidden" name="group" value="{{ $groupBy }}" />
            <x-filter-field :label="__('Dispositionsstatus')" for="board-status">
                <select id="board-status" name="status" class="select select-bordered select-sm">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option->value }}" @selected($selectedStatus === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Priorität')" for="board-priority">
                <select id="board-priority" name="priority" class="select select-bordered select-sm">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($priorityOptions as $option)
                        <option value="{{ $option->value }}" @selected($selectedPriority === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($selectableUsers !== null)
                <x-filter-field :label="__('Mitarbeiter')" for="board-user">
                    <select id="board-user" name="user" class="select select-bordered select-sm">
                        <option value="all">{{ __('alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->sqid }}" @selected($targetUser?->sqid === $u->sqid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
        </x-filter-bar>

        <div class="text-sm text-base-content/70">
            {{ trans_choice(':count Auftrag|:count Aufträge', $total, ['count' => $total]) }}
            · {{ $from->fdate() }} – {{ $to->fdate() }}
        </div>

        @if ($groupBy === 'employee')
            {{-- Swimlanes je Mitarbeiter --}}
            <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-auto" data-dispatch-board>
                @forelse ($employees as $userId => $lane)
                    <section class="rounded-box border border-base-300 bg-base-100 shadow-xs"
                             data-dispatch-lane data-user="{{ $userId }}">
                        <header class="flex items-center justify-between border-b border-base-300 px-3 py-2">
                            <span class="font-['Space_Grotesk'] font-semibold">
                                {{ $lane['name'] !== '' ? $lane['name'] : __('Nicht zugewiesen') }}
                            </span>
                            <span class="badge badge-sm">{{ count($lane['items']) }}</span>
                        </header>
                        <div class="grid grid-cols-1 gap-2 p-2 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($lane['items'] as $item)
                                @include('dispatch._board_card', ['item' => $item])
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="px-2 py-8 text-center text-sm text-base-content/50">{{ __('Keine Aufträge im gewählten Zeitraum.') }}</p>
                @endforelse
            </div>
        @else
            {{-- Spalten je Dispositionsstatus --}}
            <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5"
                 data-dispatch-board>
                @foreach ($statusOptions as $option)
                    @php $items = $columns[$option->value] ?? []; @endphp
                    <section class="flex min-h-0 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs"
                             data-dispatch-column data-status="{{ $option->value }}">
                        <header class="flex items-center justify-between border-b border-base-300 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="wd-week-legend wd-week-entry--{{ $option->tone() }}"></span>
                                <span class="font-['Space_Grotesk'] font-semibold">{{ $option->label() }}</span>
                            </div>
                            <span class="badge badge-sm" data-dispatch-count>{{ count($items) }}</span>
                        </header>
                        <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-auto p-2" data-dispatch-list>
                            @foreach ($items as $item)
                                @include('dispatch._board_card', ['item' => $item])
                            @endforeach
                            <p class="px-2 py-4 text-center text-xs text-base-content/50 {{ count($items) ? 'hidden' : '' }}">{{ __('Keine Aufträge') }}</p>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </x-index-page>
@endsection
