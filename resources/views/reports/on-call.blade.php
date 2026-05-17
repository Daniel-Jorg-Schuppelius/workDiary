@extends('layouts.app')
@section('title', __('Notdienst-Auswertung'))
@section('nav-title', __('Notdienst-Auswertung'))

@section('content')
@php
    $fmt = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $pct = fn (float $v) => number_format($v * 100, 1, ',', '.') . ' %';
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.on-call')" :reset="route('reports.on-call')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine Bereitschaft') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.on-call', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.on-call', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
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
            <div class="stat-title">{{ __('Bereitschaft') }}</div>
            <div class="stat-value text-2xl">{{ $fmt($totals['shift_minutes']) }}</div>
            <div class="stat-desc">{{ $totals['shift_count'] }} {{ __('Schichten') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Aktiv-Einsätze') }}</div>
            <div class="stat-value text-2xl">{{ $fmt($totals['assignment_minutes']) }}</div>
            <div class="stat-desc">{{ $totals['assignment_count'] }} {{ __('Einsätze') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Aktiv-Anteil') }}</div>
            <div class="stat-value text-2xl">
                {{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if (empty($rows))
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Bereitschaftszeiten im gewählten Zeitraum.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="text-right">{{ __('Schichten') }}</th>
                            <th class="text-right">{{ __('Bereitschaft') }}</th>
                            <th class="text-right">{{ __('Einsätze') }}</th>
                            <th class="text-right">{{ __('Einsatzzeit') }}</th>
                            <th class="text-right">{{ __('Aktiv-Anteil') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="font-semibold">{{ $r['user']->name }}</td>
                                <td class="text-right tabular-nums">{{ $r['shift_count'] }}</td>
                                <td class="text-right tabular-nums">{{ $fmt($r['shift_minutes']) }}</td>
                                <td class="text-right tabular-nums">{{ $r['assignment_count'] }}</td>
                                <td class="text-right tabular-nums">{{ $fmt($r['assignment_minutes']) }}</td>
                                <td class="text-right tabular-nums">{{ $r['ratio'] !== null ? $pct($r['ratio']) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>{{ __('Gesamt') }}</td>
                            <td class="text-right tabular-nums">{{ $totals['shift_count'] }}</td>
                            <td class="text-right tabular-nums">{{ $fmt($totals['shift_minutes']) }}</td>
                            <td class="text-right tabular-nums">{{ $totals['assignment_count'] }}</td>
                            <td class="text-right tabular-nums">{{ $fmt($totals['assignment_minutes']) }}</td>
                            <td class="text-right tabular-nums">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
