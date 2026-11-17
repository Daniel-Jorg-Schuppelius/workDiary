{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Vertretungen (Feature 202, MVP-1647). Variablen: $delegations (Paginator), $work (ID → übernommene Bereiche), $today, $canManage, $filters (all, state). --}}

@extends('layouts.app')

@section('title', __('delegation.title'))
@section('nav-title', __('delegation.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('delegation.subtitle')">
    <x-slot:actions>
        {{-- Endgültige Übergabe ohne Austritt (MVP-1648): Einstieg für die Leitung, auch ohne Zugang zur Mitgliederliste. --}}
        @if ($canManage)
            <x-icon-btn icon="move_up" size="sm"
                        data-entry-modal-trigger
                        :href="route('delegations.handover.choose')"
                        show-label>{{ __('delegation.handover.action') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('delegations.create') . '?dialog=1'"
                    show-label>{{ __('delegation.create') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('delegations.index')" :reset="route('delegations.index')">
        <select name="state" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('delegation.filter.state') }}">
            <option value="current" @selected($filters['state'] === 'current')>{{ __('delegation.filter.current') }}</option>
            <option value="past" @selected($filters['state'] === 'past')>{{ __('delegation.filter.past') }}</option>
        </select>
        @if ($canManage)
            <x-filter-toggle name="all" :label="__('delegation.filter.all')" :checked="$filters['all']" />
        @endif
    </x-filter-bar>

    @if ($delegations->isEmpty())
        <x-empty-state framed icon="supervisor_account" :title="__('delegation.empty.title')" :message="__('delegation.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('delegation.column.represented') }}</th>
                    <th>{{ __('delegation.column.deputy') }}</th>
                    <th>{{ __('delegation.column.period') }}</th>
                    <th>{{ __('delegation.column.state') }}</th>
                    <th>{{ __('delegation.column.work') }}</th>
                    <th class="w-px"></th>
                </tr>
            </x-slot:head>
            @foreach ($delegations as $delegation)
                @php($state = $delegation->stateOn($today))
                <tr class="hover">
                    <td class="font-medium">
                        {{ $delegation->represented?->name ?? '—' }}
                        @can('viewReason', $delegation)
                            <div class="text-xs text-muted" data-delegation-reason>
                                <x-icon :name="$delegation->kind->icon()" class="align-middle" /> {{ $delegation->kind->label() }}
                                @if ($delegation->note) · {{ $delegation->note }}@endif
                            </div>
                        @endcan
                    </td>
                    <td>{{ $delegation->deputy?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap text-sm tabular-nums">
                        {{ $delegation->valid_from->fdate() }} – {{ $delegation->valid_until?->fdate() ?? __('delegation.open_end') }}
                    </td>
                    <td>
                        <x-status-badge size="sm" :tone="match ($state) { 'active' => 'success', 'upcoming' => 'info', default => 'ghost' }">{{ __('delegation.state.' . $state) }}</x-status-badge>
                        @if ($state === 'ended')
                            <div class="text-xs text-muted">{{ __('delegation.ended_by', ['name' => $delegation->ender?->name ?? '—', 'date' => $delegation->ended_at?->fdate() ?? '—']) }}</div>
                        @endif
                    </td>
                    <td class="text-sm">
                        @forelse ($work[$delegation->id] ?? [] as $area)
                            <span class="whitespace-nowrap">{{ $area['label'] }}: <span class="tabular-nums">{{ $area['count'] }}</span></span>@if (! $loop->last)<span class="text-muted"> · </span>@endif
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td class="text-right">
                        @can('end', $delegation)
                            <x-action-form :action="route('delegations.end', $delegation)" method="PATCH"
                                           :confirm="__('delegation.end_confirm')"
                                           :confirm-label="__('delegation.end')">
                                <x-icon-btn icon="stop_circle" tone="warning" type="submit" :label="__('delegation.end')" />
                            </x-action-form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$delegations" standing />
    @endif
</x-index-page>
@endsection
