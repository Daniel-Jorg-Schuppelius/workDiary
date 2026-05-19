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
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.absences', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.absences', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                        show-label>PDF</x-icon-btn>
        </x-slot:extra>
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['users'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Urlaub (Werktage)') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['vacation_days'] }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ $totals['pending_days'] }} {{ __('ausstehend') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Krank') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['sick_days'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Sonder / Unbezahlt') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['special_days'] }} / {{ $totals['unpaid_days'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Flex-Änderung Σ') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold {{ $totals['flex_change_minutes'] < 0 ? 'text-error' : ($totals['flex_change_minutes'] > 0 ? 'text-success' : '') }}">
                {{ $fmtMin($totals['flex_change_minutes']) }}
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
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
    </div>
</x-page-shell>
@endsection
