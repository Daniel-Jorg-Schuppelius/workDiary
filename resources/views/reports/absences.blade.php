@extends('layouts.app')
@section('title', __('Urlaub & Flex'))
@section('nav-title', __('Urlaub & Flex'))

@section('content')
@php
    $fmtMin = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : ($minutes > 0 ? '+' : '');
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp

<x-page-shell>

    <x-filter-bar :action="route('reports.absences')" :reset="route('reports.absences')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.absences', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.absences', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('Mitarbeiter') }}</div>
            <div class="stat-value text-2xl">{{ $totals['users'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Urlaub (Werktage)') }}</div>
            <div class="stat-value text-2xl">{{ $totals['vacation_days'] }}</div>
            <div class="stat-desc">{{ $totals['pending_days'] }} {{ __('ausstehend') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Krank') }}</div>
            <div class="stat-value text-2xl">{{ $totals['sick_days'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Sonder / Unbezahlt') }}</div>
            <div class="stat-value text-2xl">{{ $totals['special_days'] }} / {{ $totals['unpaid_days'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Flex-Änderung Σ') }}</div>
            <div class="stat-value text-2xl {{ $totals['flex_change_minutes'] < 0 ? 'text-error' : ($totals['flex_change_minutes'] > 0 ? 'text-success' : '') }}">
                {{ $fmtMin($totals['flex_change_minutes']) }}
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if (empty($rows))
            <x-empty-state :title="__('Keine Abwesenheits- oder Flex-Daten im gewählten Zeitraum.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="text-right">{{ __('Urlaub') }}</th>
                            <th class="text-right">{{ __('Krank') }}</th>
                            <th class="text-right">{{ __('Sonder') }}</th>
                            <th class="text-right">{{ __('Unbezahlt') }}</th>
                            <th class="text-right">{{ __('Ausstehend') }}</th>
                            <th class="text-right">{{ __('Flex Δ') }}</th>
                            <th class="text-right">{{ __('Flex-Saldo') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="font-semibold">{{ $r['user']->name }}</td>
                                <td class="text-right tabular-nums">{{ $r['vacation_days'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['sick_days'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['special_days'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['unpaid_days'] }}</td>
                                <td class="text-right tabular-nums {{ $r['pending_days'] > 0 ? 'text-warning' : '' }}">{{ $r['pending_days'] }}</td>
                                <td class="text-right tabular-nums {{ $r['flex_change_minutes'] < 0 ? 'text-error' : ($r['flex_change_minutes'] > 0 ? 'text-success' : '') }}">
                                    {{ $fmtMin($r['flex_change_minutes']) }}
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ $r['flex_balance_minutes'] !== null ? $fmtMin($r['flex_balance_minutes']) : '–' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>{{ __('Gesamt') }}</td>
                            <td class="text-right tabular-nums">{{ $totals['vacation_days'] }}</td>
                            <td class="text-right tabular-nums">{{ $totals['sick_days'] }}</td>
                            <td class="text-right tabular-nums">{{ $totals['special_days'] }}</td>
                            <td class="text-right tabular-nums">{{ $totals['unpaid_days'] }}</td>
                            <td class="text-right tabular-nums">{{ $totals['pending_days'] }}</td>
                            <td class="text-right tabular-nums">{{ $fmtMin($totals['flex_change_minutes']) }}</td>
                            <td class="text-right tabular-nums">{{ $fmtMin($totals['flex_balance_minutes']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
