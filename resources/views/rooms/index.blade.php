{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Räume'))
@section('nav-title', __('Räume'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $rooms */
    /** @var string $view */
    /** @var \Carbon\Carbon $day */
    /** @var array $grid */
    /** @var \Illuminate\Support\Collection $gridRooms */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Räume und Standorte des Mandanten verwalten.')">
        <x-slot:actions>
            <a href="{{ route('rooms.index', ['view' => 'list']) }}"
               class="btn btn-sm {{ $view === 'list' ? 'btn-primary' : 'btn-ghost' }}">
                <x-icon name="list" /> {{ __('Liste') }}
            </a>
            <a href="{{ route('rooms.index', ['view' => 'grid', 'day' => $day->format('Y-m-d')]) }}"
               class="btn btn-sm {{ $view === 'grid' ? 'btn-primary' : 'btn-ghost' }}">
                <x-icon name="grid_view" /> {{ __('Tages-Belegung') }}
            </a>
            @can('create', App\Models\Room::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('rooms.create').'?dialog=1'"
                            show-label>{{ __('Neuer Raum') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        @if ($view === 'grid')
            <div class="card bg-base-100 shadow">
                <div class="card-body p-3 gap-3">
                    <div class="flex items-center gap-2">
                        <x-icon-btn icon="chevron_left" tone="ghost" size="xs"
                                    :href="route('rooms.index', ['view' => 'grid', 'day' => $day->copy()->subDay()->format('Y-m-d')])"
                                    :label="__('Vortag')" />
                        <span class="font-semibold">{{ $day->isoFormat('dddd, LL') }}</span>
                        <x-icon-btn icon="chevron_right" tone="ghost" size="xs"
                                    :href="route('rooms.index', ['view' => 'grid', 'day' => $day->copy()->addDay()->format('Y-m-d')])"
                                    :label="__('Folgetag')" />
                        <a href="{{ route('rooms.index', ['view' => 'grid']) }}" class="btn btn-xs btn-ghost">{{ __('Heute') }}</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm w-full">
                            <thead class="bg-base-200">
                                <tr>
                                    <th class="w-40">{{ __('Raum') }}</th>
                                    @for ($h = 6; $h <= 22; $h++)
                                        <th class="text-center text-xs font-normal">{{ sprintf('%02d', $h) }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($gridRooms as $room)
                                    @php $bookings = $grid[$room->id] ?? []; @endphp
                                    <tr class="hover">
                                        <td class="font-semibold">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $room->color ?? '#999' }}"></span>
                                                {{ $room->name }}
                                            </div>
                                            @if ($room->capacity)
                                                <div class="text-xs opacity-70">{{ $room->capacity }} {{ __('Plätze') }}</div>
                                            @endif
                                        </td>
                                        <td colspan="17" class="relative p-0" style="height:48px">
                                            @foreach ($bookings as $b)
                                                @php
                                                    $start = $b['started_at'];
                                                    $end   = $b['ended_at'];
                                                    $ev    = $b['event'];
                                                    $fromH = max(6, min(22, $start->hour + $start->minute / 60));
                                                    $toH   = max(6, min(23, $end->hour + $end->minute / 60));
                                                    $left  = (($fromH - 6) / 17) * 100;
                                                    $width = max(0.5, (($toH - $fromH) / 17) * 100);
                                                @endphp
                                                <a href="{{ route('events.show', $ev) }}"
                                                   class="absolute top-1 bottom-1 rounded px-1 text-xs text-white overflow-hidden whitespace-nowrap"
                                                   style="left:{{ $left }}%;width:{{ $width }}%;background:{{ $ev->category?->color ?? '#3b82f6' }}"
                                                   title="{{ $ev->title }} ({{ $start->format('H:i') }}–{{ $end->format('H:i') }})">
                                                    {{ $ev->title }}
                                                </a>
                                            @endforeach
                                        </td>
                                    </tr>
                                @empty
                                    <x-table.empty :colspan="18" :title="__('Keine aktiven Räume')" compact />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <x-filter-bar :action="route('rooms.index')" method="GET" :reset="route('rooms.index')">
                <input type="hidden" name="view" value="list" />
                <input type="text" name="q" value="{{ $search ?? '' }}"
                       class="input input-sm input-bordered w-48 shrink-0"
                       placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
            </x-filter-bar>

            <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="server"
                     :route="route('rooms.index')" :current-sort="$sort" :current-dir="$dir"
                     :sort-params="['view' => 'list', 'q' => $search ?: null]">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                        <x-table.th sort="code">{{ __('Code') }}</x-table.th>
                        <th>{{ __('Gebäude / Etage') }}</th>
                        <x-table.th sort="capacity" align="right">{{ __('Kapazität') }}</x-table.th>
                        <th>{{ __('Ausstattung') }}</th>
                        <x-table.th sort="is_active">{{ __('Status') }}</x-table.th>
                        <th class="w-32 text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                    @forelse ($rooms as $room)
                        <tr class="hover">
                            <td>
                                <div class="flex items-center gap-2 font-semibold">
                                    <span class="inline-block w-3 h-3 rounded" style="background:{{ $room->color ?? '#999' }}"></span>
                                    {{ $room->name }}
                                </div>
                            </td>
                            <td><span class="font-mono">{{ $room->code }}</span></td>
                            <td class="whitespace-nowrap">
                                {{ $room->building }}@if ($room->floor) · {{ $room->floor }} @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $room->capacity }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach (($room->equipment ?? []) as $eq)
                                        <x-status-badge tone="ghost">{{ __("values.$eq") }}</x-status-badge>
                                    @endforeach
                                    @foreach ($room->requirements as $req)
                                        <x-status-badge tone="warning" :title="$req->note ?? ''">
                                            {{ $req->kind->label() }}@if ($req->level): {{ $req->level }}@endif
                                        </x-status-badge>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if ($room->is_active)
                                    <x-status-badge tone="success">{{ __('Aktiv') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="ghost">{{ __('Inaktiv') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @can('update', $room)
                                    <x-icon-btn icon="edit"
                                                data-entry-modal-trigger
                                                :href="route('rooms.edit', $room).'?dialog=1'"
                                                :label="__('Bearbeiten')" />
                                @endcan
                                @can('delete', $room)
                                    <x-action-form :action="route('rooms.destroy', $room)" method="DELETE"
                                          :confirm="__('Raum wirklich löschen?')"
                                          :confirm-label="__('Löschen')">
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </x-action-form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7"
                            icon="meeting_room"
                            :title="__('Noch keine Räume angelegt')" compact />
                    @endforelse
            </x-table>

            <x-pagination :paginator="$rooms" standing />
        @endif
    </x-index-page>
@endsection
