{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gewährleistungsfristen (Feature 115, MVP-604): eigene Haftung und
  einforderbare Sub-Fristen nebeneinander — daraus wird sichtbar, welche
  Sub-Frist VOR der eigenen endet.
--}}

@extends('layouts.app')

@section('title', __('warranty.title'))
@section('nav-title', __('warranty.title'))

@section('content')
    <x-index-page :subtitle="__('warranty.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('warranties.create')"
                        show-label>{{ __('warranty.action.create') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('warranty.kpi.owed')" :value="$openOwed" format="int"
                        :hint="__('warranty.kpi.owed_hint')" />
            <x-kpi-tile :label="__('warranty.kpi.claimable')" :value="$openClaimable" format="int"
                        :hint="__('warranty.kpi.claimable_hint')" />
            <x-kpi-tile :label="__('warranty.kpi.expiring')" :value="$expiringSoon" format="int"
                        :tone="$expiringSoon > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('warranty.kpi.critical')" :value="$critical->count()" format="int"
                        :tone="$critical->isNotEmpty() ? 'error' : 'success'"
                        :hint="__('warranty.kpi.critical_hint')" />
        </div>

        @if ($critical->isNotEmpty())
            {{-- Der teure Fall bekommt eine eigene Fläche: Wer ihn übersieht,
                 haftet allein für einen fremden Mangel. --}}
            <div class="rounded-box border border-error/40 bg-error/5 px-4 py-3">
                <p class="text-sm font-medium">{{ __('warranty.critical.heading') }}</p>
                <p class="mt-1 text-xs text-base-content/70">{{ __('warranty.critical.hint') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($critical as $period)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $period->project?->name ?? '—' }}</span>
                            <span class="text-base-content/60">{{ $period->partyLabel() }}</span>
                            @if ($period->trade !== null)<span class="text-base-content/60">{{ $period->trade }}</span>@endif
                            <x-status-badge tone="error" outline>{{ $period->ends_on->fdate() }}</x-status-badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-filter-bar :action="route('warranties.index')" :reset="route('warranties.index')">
            <x-filter-field :label="__('warranty.filter.side')" for="w-side" class="min-w-48">
                <select id="w-side" name="side" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Warranty\WarrantySide::cases() as $side)
                        <option value="{{ $side->value }}" @selected($filters['side'] === $side->value)>{{ $side->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('warranty.filter.status')" for="w-status" class="min-w-40">
                <select id="w-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Warranty\WarrantyStatus::cases() as $st)
                        <option value="{{ $st->value }}" @selected($filters['status'] === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pin-rows="true" :zebra="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('warranty.column.side') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('warranty.column.project') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('warranty.column.party') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('warranty.column.trade') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('warranty.column.basis') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('warranty.column.starts_on') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('warranty.column.ends_on') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('warranty.column.status') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($periods as $period)
                <tr class="hover">
                    <td>{{ $period->side->label() }}</td>
                    <td class="font-medium">{{ $period->project?->name ?? '—' }}</td>
                    <td>{{ $period->partyLabel() }}</td>
                    <td>{{ $period->trade ?? '—' }}</td>
                    <td>
                        {{ $period->basis->label() }}
                        @if ($period->isOverridden())
                            <span class="text-xs text-warning" title="{{ $period->override_reason }}">{{ __('warranty.overridden') }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $period->starts_on->fdate() }}</td>
                    <td class="whitespace-nowrap">
                        <x-status-badge :tone="$period->isRunning() ? 'neutral' : 'warning'" outline>{{ $period->ends_on->fdate() }}</x-status-badge>
                    </td>
                    <td>{{ $period->status->label() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end">
                            @if ($period->status === \App\Enums\Warranty\WarrantyStatus::Open)
                                <x-action-form :action="route('warranties.close', $period)">
                                    <x-icon-btn icon="task_alt" size="xs" tone="ghost" type="submit"
                                                :label="__('warranty.action.close')" />
                                </x-action-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty-state icon="shield_with_heart" :title="__('warranty.empty')" /></td></tr>
            @endforelse
        </x-table>

        <x-pagination :paginator="$periods" standing />
    </x-index-page>
@endsection
