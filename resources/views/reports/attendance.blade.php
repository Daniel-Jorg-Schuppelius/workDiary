@extends('layouts.app')
@section('title', __('Anwesenheit'))
@section('nav-title', __('Anwesenheits-Auswertung'))

@section('content')
@php
    $fmtMin = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Soll, Anwesenheit, gebuchte Zeit und Saldo je Mitarbeiter im Zeitraum.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.attendance', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.attendance', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.attendance')" :reset="route('reports.attendance')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Soll')" :value="$fmtMin($totals['target'])" />
        <x-kpi-tile :label="__('Anwesend')" :value="$fmtMin($totals['attendance'])" />
        <x-kpi-tile :label="__('Gebucht')" :value="$fmtMin($totals['time_entry'])" />
        <x-kpi-tile :label="__('Saldo')" :value="$fmtMin($totals['variance'])"
                    :tone="$totals['variance'] < 0 ? 'error' : ($totals['variance'] > 0 ? 'success' : 'neutral')" />
    </div>

    <x-card>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">schedule</span>' :title="__('Keine Daten im Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Arbeitstage') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Soll') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Anwesend') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Gebucht') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Saldo') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td></td>
                        <td class="text-right tabular-nums">{{ $fmtMin($totals['target']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmtMin($totals['attendance']) }}</td>
                        <td class="text-right tabular-nums">{{ $fmtMin($totals['time_entry']) }}</td>
                        <td class="text-right tabular-nums {{ $totals['variance'] < 0 ? 'text-error' : ($totals['variance'] > 0 ? 'text-success' : '') }}">{{ $fmtMin($totals['variance']) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['user']->name }}</td>
                        <td class="text-right tabular-nums">{{ $r['workdays'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['target_minutes'] }}">{{ $fmtMin($r['target_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['attendance_minutes'] }}">{{ $fmtMin($r['attendance_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['time_entry_minutes'] }}">{{ $fmtMin($r['time_entry_minutes']) }}</td>
                        <td class="text-right tabular-nums {{ $r['variance'] < 0 ? 'text-error font-semibold' : ($r['variance'] > 0 ? 'text-success' : '') }}" data-sort-value="{{ (int) $r['variance'] }}">{{ $fmtMin($r['variance']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
