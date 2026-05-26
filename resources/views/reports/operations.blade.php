@extends('layouts.app')
@section('title', __('Operations'))
@section('nav-title', __('Operations-Auswertung'))

@section('content')
@php
    $pct = fn (?float $v) => $v !== null ? number_format($v * 100, 1, ',', '.') . ' %' : '–';
    $fmtMin = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $num = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
    $statusLabels = [
        'planned'      => __('Geplant'),
        'assigned'     => __('Zugewiesen'),
        'in_progress'  => __('In Arbeit'),
        'done'         => __('Erledigt'),
        'cancelled'    => __('Storniert'),
        'open'         => __('Offen'),
        'completed'    => __('Abgeschlossen'),
        'draft'        => __('Entwurf'),
    ];
    $prioLabels = [
        'low'    => __('Niedrig'),
        'normal' => __('Normal'),
        'medium' => __('Mittel'),
        'high'   => __('Hoch'),
        'urgent' => __('Dringend'),
    ];
    $label = fn (array $map, string $key) => $map[$key] ?? $key;
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Service-Aufträge, Tasks und Touren auf einen Blick.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.operations', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.operations', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.operations')" :reset="route('reports.operations')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Service-Aufträge') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $orders['total'] }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ __('Abschluss') }}: {{ $pct($orders['completion_rate']) }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Servicezeit Σ') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $fmtMin($orders['service_minutes']) }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Tasks') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $tasks['total'] }}</div>
            <div class="mt-1 text-xs text-base-content/60 {{ $tasks['overdue'] > 0 ? 'text-error' : '' }}">
                {{ $tasks['overdue'] }} {{ __('überfällig') }} · {{ $pct($tasks['completion_rate']) }}
            </div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Touren') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $tours['total'] }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ $num($tours['planned_distance_km'], 1) }} km · {{ $fmtMin($tours['planned_minutes']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Service-Aufträge – Status') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($orders['by_status'] as $st => $c)
                    <tr><td>{{ $label($statusLabels, $st) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
            <h3 class="mt-4 mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Service-Aufträge – Priorität') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Priorität') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($orders['by_priority'] as $p => $c)
                    <tr><td>{{ $label($prioLabels, $p) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Tasks – Status') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($tasks['by_status'] as $st => $c)
                    <tr><td>{{ $label($statusLabels, $st) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
            <h3 class="mt-4 mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Tasks – Priorität') }}</h3>
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Priorität') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Anzahl') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($tasks['by_priority'] as $p => $c)
                    <tr><td>{{ $label($prioLabels, $p) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                @endforeach
            </x-table>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Touren – pro Mitarbeiter') }}</h3>
        @if (empty($tours['per_user']))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">engineering</span>' :title="__('Keine Touren im Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Touren') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Plan-km') }}</x-table.th>
                        <x-table.th sort type="duration" align="right">{{ __('Plan-Dauer') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $tours['total'] }}</td>
                        <td class="text-right tabular-nums">{{ $num($tours['planned_distance_km'], 1) }} km</td>
                        <td class="text-right tabular-nums">{{ $fmtMin($tours['planned_minutes']) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($tours['per_user'] as $u)
                    <tr>
                        <td class="font-semibold">{{ $u['user']->name }}</td>
                        <td class="text-right tabular-nums">{{ $u['count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $u['distance_km'] }}">{{ $num($u['distance_km'], 1) }} km</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $u['minutes'] }}">{{ $fmtMin($u['minutes']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection
