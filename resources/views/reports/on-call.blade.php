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

<x-page-shell>

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
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.on-call', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.on-call', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                        show-label>PDF</x-icon-btn>
        </x-slot:extra>
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['users'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Bereitschaft') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $fmt($totals['shift_minutes']) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ $totals['shift_count'] }} {{ __('Schichten') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Aktiv-Einsätze') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $fmt($totals['assignment_minutes']) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ $totals['assignment_count'] }} {{ __('Einsätze') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Aktiv-Anteil') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">
                {{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :title="__('Keine Bereitschaftszeiten im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Schichten') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Bereitschaft') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Einsätze') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Einsatzzeit') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Aktiv-Anteil') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $totals['shift_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($totals['shift_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $totals['assignment_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($totals['assignment_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['user']->name }}</td>
                        <td class="text-right tabular-nums">{{ $r['shift_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['shift_minutes'] }}">{{ $fmt($r['shift_minutes']) }}</td>
                        <td class="text-right tabular-nums">{{ $r['assignment_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['assignment_minutes'] }}">{{ $fmt($r['assignment_minutes']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $r['ratio'] ?? -1 }}">{{ $r['ratio'] !== null ? $pct($r['ratio']) : '–' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection
