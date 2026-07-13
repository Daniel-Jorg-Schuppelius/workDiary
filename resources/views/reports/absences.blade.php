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
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Urlaub, Krankheit, Sonderfreistellungen und Flex-Saldo je Mitarbeiter.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.absences', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.absences', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.absences')" :reset="route('reports.absences')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Mitarbeiter')" :value="$totals['users']" />
        <x-kpi-tile :label="__('Urlaub (Werktage)')" :value="$totals['vacation_days']" :hint="$totals['pending_days'] . ' ' . __('ausstehend')" />
        <x-kpi-tile :label="__('Krank')" :value="$totals['sick_days']" />
        <x-kpi-tile :label="__('Sonder / Unbezahlt')" :value="$totals['special_days'] . ' / ' . $totals['unpaid_days']" />
        <x-kpi-tile :label="__('Flex-Änderung Σ')" :value="$fmtMin($totals['flex_change_minutes'])"
                    :tone="$totals['flex_change_minutes'] < 0 ? 'error' : ($totals['flex_change_minutes'] > 0 ? 'success' : 'neutral')" />
    </div>

    <x-card>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">event_busy</span>' :title="__('Keine Abwesenheits- oder Flex-Daten im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Urlaub') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Krank') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Sonder') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Unbezahlt') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Ausstehend') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Flex Δ') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Flex-Saldo') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
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
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['user']->name }}</td>
                        <td class="text-right tabular-nums">{{ $r['vacation_days'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['sick_days'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['special_days'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['unpaid_days'] }}</td>
                        <td class="text-right tabular-nums {{ $r['pending_days'] > 0 ? 'text-warning' : '' }}">{{ $r['pending_days'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['flex_change_minutes'] }}">
                            <span class="{{ $r['flex_change_minutes'] < 0 ? 'text-error' : ($r['flex_change_minutes'] > 0 ? 'text-success' : '') }}">{{ $fmtMin($r['flex_change_minutes']) }}</span>
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) ($r['flex_balance_minutes'] ?? 0) }}">
                            {{ $r['flex_balance_minutes'] !== null ? $fmtMin($r['flex_balance_minutes']) : '–' }}
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
