{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : presence.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Plan/Ist Anwesenheit'))
@section('nav-title', __('Plan/Ist Anwesenheit'))

@php
    $fmt = fn(int $m) => sprintf('%d:%02d', intdiv(abs($m), 60), abs($m) % 60) . ($m < 0 ? ' −' : '');
@endphp

@section('content')
    <x-index-page :subtitle="__('Soll-Arbeitszeit vs. tatsächlich gestempelte Anwesenheit.')">
        <x-filter-bar :action="route('reports.plan-ist.presence')" :reset="route('reports.plan-ist.presence')">
            <label class="flex items-center gap-1 text-xs">
                <span>{{ __('Von') }}</span>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"
                       class="input input-sm input-bordered w-36 shrink-0" />
            </label>
            <label class="flex items-center gap-1 text-xs">
                <span>{{ __('Bis') }}</span>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"
                       class="input input-sm input-bordered w-36 shrink-0" />
            </label>
        </x-filter-bar>

        <div class="stats bg-base-200 shadow-sm mb-3">
            <div class="stat">
                <div class="stat-title">{{ __('Plan') }}</div>
                <div class="stat-value text-base">{{ $fmt($totals['plan_minutes']) }}</div>
            </div>
            <div class="stat">
                <div class="stat-title">{{ __('Ist') }}</div>
                <div class="stat-value text-base">{{ $fmt($totals['actual_minutes']) }}</div>
            </div>
            <div class="stat">
                <div class="stat-title">{{ __('Δ') }}</div>
                <div class="stat-value text-base {{ $totals['delta_minutes'] < 0 ? 'text-error' : 'text-success' }}">
                    {{ $fmt($totals['delta_minutes']) }}
                </div>
            </div>
            <div class="stat">
                <div class="stat-title">{{ __('Warnungen') }}</div>
                <div class="stat-value text-base">{{ $totals['warnings'] }}</div>
            </div>
        </div>

        <x-table table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date" default="asc">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Plan') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Ist') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Δ') }}</x-table.th>
                    <th>{{ __('Start P/I') }}</th>
                    <th>{{ __('Warnungen') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $r)
                <tr>
                    <td class="font-medium" data-sort-value="{{ \Carbon\CarbonImmutable::parse($r['date'])->format('Y-m-d') }}">{{ \Carbon\CarbonImmutable::parse($r['date'])->format('D d.m.') }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ $r['no_plan'] ? '' : $r['plan_minutes'] }}">
                        @if ($r['no_plan'])<span class="text-base-content/40">—</span>
                        @else{{ $fmt($r['plan_minutes']) }}@endif
                    </td>
                    <td class="text-right tabular-nums" data-sort-value="{{ $r['actual_minutes'] }}">{{ $fmt($r['actual_minutes']) }}</td>
                    <td class="text-right tabular-nums {{ $r['delta_minutes'] < 0 ? 'text-error' : '' }}" data-sort-value="{{ $r['delta_minutes'] }}">
                        {{ $fmt($r['delta_minutes']) }}
                    </td>
                    <td class="text-xs">
                        {{ $r['plan_start'] ?? '—' }} / {{ $r['actual_start'] ?? '—' }}
                        @if ($r['late_start_minutes'] !== null)
                            <span class="text-base-content/60">({{ $r['late_start_minutes'] > 0 ? '+' : '' }}{{ $r['late_start_minutes'] }}m)</span>
                        @endif
                    </td>
                    <td>
                        @foreach ($r['warnings'] as $w)
                            <x-status-badge tone="warning" size="xs">{{ $w }}</x-status-badge>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-index-page>
@endsection
