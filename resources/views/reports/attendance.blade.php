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

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.attendance')" :reset="route('reports.attendance')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.attendance', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.attendance', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat"><div class="stat-title">{{ __('Soll') }}</div><div class="stat-value text-2xl">{{ $fmtMin($totals['target']) }}</div></div>
        <div class="stat"><div class="stat-title">{{ __('Anwesend') }}</div><div class="stat-value text-2xl">{{ $fmtMin($totals['attendance']) }}</div></div>
        <div class="stat"><div class="stat-title">{{ __('Gebucht') }}</div><div class="stat-value text-2xl">{{ $fmtMin($totals['time_entry']) }}</div></div>
        <div class="stat">
            <div class="stat-title">{{ __('Saldo') }}</div>
            <div class="stat-value text-2xl {{ $totals['variance'] < 0 ? 'text-error' : ($totals['variance'] > 0 ? 'text-success' : '') }}">{{ $fmtMin($totals['variance']) }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if (empty($rows))
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Daten im Zeitraum.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="text-right">{{ __('Arbeitstage') }}</th>
                            <th class="text-right">{{ __('Soll') }}</th>
                            <th class="text-right">{{ __('Anwesend') }}</th>
                            <th class="text-right">{{ __('Gebucht') }}</th>
                            <th class="text-right">{{ __('Saldo') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="font-semibold">{{ $r['user']->name }}</td>
                                <td class="text-right tabular-nums">{{ $r['workdays'] }}</td>
                                <td class="text-right tabular-nums">{{ $fmtMin($r['target_minutes']) }}</td>
                                <td class="text-right tabular-nums">{{ $fmtMin($r['attendance_minutes']) }}</td>
                                <td class="text-right tabular-nums">{{ $fmtMin($r['time_entry_minutes']) }}</td>
                                <td class="text-right tabular-nums {{ $r['variance'] < 0 ? 'text-error font-semibold' : ($r['variance'] > 0 ? 'text-success' : '') }}">{{ $fmtMin($r['variance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>{{ __('Gesamt') }}</td>
                            <td></td>
                            <td class="text-right tabular-nums">{{ $fmtMin($totals['target']) }}</td>
                            <td class="text-right tabular-nums">{{ $fmtMin($totals['attendance']) }}</td>
                            <td class="text-right tabular-nums">{{ $fmtMin($totals['time_entry']) }}</td>
                            <td class="text-right tabular-nums {{ $totals['variance'] < 0 ? 'text-error' : ($totals['variance'] > 0 ? 'text-success' : '') }}">{{ $fmtMin($totals['variance']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
