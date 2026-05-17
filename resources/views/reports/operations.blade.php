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

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    <x-filter-bar :action="route('reports.operations')" :reset="route('reports.operations')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.operations', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.operations', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('ServiceOrders') }}</div>
            <div class="stat-value text-2xl">{{ $orders['total'] }}</div>
            <div class="stat-desc">{{ __('Abschluss') }}: {{ $pct($orders['completion_rate']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Servicezeit Σ') }}</div>
            <div class="stat-value text-2xl">{{ $fmtMin($orders['service_minutes']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Tasks') }}</div>
            <div class="stat-value text-2xl">{{ $tasks['total'] }}</div>
            <div class="stat-desc {{ $tasks['overdue'] > 0 ? 'text-error' : '' }}">
                {{ $tasks['overdue'] }} {{ __('überfällig') }} · {{ $pct($tasks['completion_rate']) }}
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Touren') }}</div>
            <div class="stat-value text-2xl">{{ $tours['total'] }}</div>
            <div class="stat-desc">{{ $num($tours['planned_distance_km'], 1) }} km · {{ $fmtMin($tours['planned_minutes']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('ServiceOrders – Status') }}</h3>
            <table class="table table-zebra table-sm">
                <thead><tr><th>{{ __('Status') }}</th><th class="text-right">{{ __('Anzahl') }}</th></tr></thead>
                <tbody>
                    @foreach ($orders['by_status'] as $st => $c)
                        <tr><td>{{ $label($statusLabels, $st) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <h3 class="mt-4 mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('ServiceOrders – Priorität') }}</h3>
            <table class="table table-zebra table-sm">
                <thead><tr><th>{{ __('Priorität') }}</th><th class="text-right">{{ __('Anzahl') }}</th></tr></thead>
                <tbody>
                    @foreach ($orders['by_priority'] as $p => $c)
                        <tr><td>{{ $label($prioLabels, $p) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Tasks – Status') }}</h3>
            <table class="table table-zebra table-sm">
                <thead><tr><th>{{ __('Status') }}</th><th class="text-right">{{ __('Anzahl') }}</th></tr></thead>
                <tbody>
                    @foreach ($tasks['by_status'] as $st => $c)
                        <tr><td>{{ $label($statusLabels, $st) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <h3 class="mt-4 mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Tasks – Priorität') }}</h3>
            <table class="table table-zebra table-sm">
                <thead><tr><th>{{ __('Priorität') }}</th><th class="text-right">{{ __('Anzahl') }}</th></tr></thead>
                <tbody>
                    @foreach ($tasks['by_priority'] as $p => $c)
                        <tr><td>{{ $label($prioLabels, $p) }}</td><td class="text-right tabular-nums">{{ $c }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Touren – pro Mitarbeiter') }}</h3>
        @if (empty($tours['per_user']))
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Touren im Zeitraum.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="text-right">{{ __('Touren') }}</th>
                            <th class="text-right">{{ __('Plan-km') }}</th>
                            <th class="text-right">{{ __('Plan-Dauer') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tours['per_user'] as $u)
                            <tr>
                                <td class="font-semibold">{{ $u['user']->name }}</td>
                                <td class="text-right tabular-nums">{{ $u['count'] }}</td>
                                <td class="text-right tabular-nums">{{ $num($u['distance_km'], 1) }} km</td>
                                <td class="text-right tabular-nums">{{ $fmtMin($u['minutes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td>{{ __('Gesamt') }}</td>
                            <td class="text-right tabular-nums">{{ $tours['total'] }}</td>
                            <td class="text-right tabular-nums">{{ $num($tours['planned_distance_km'], 1) }} km</td>
                            <td class="text-right tabular-nums">{{ $fmtMin($tours['planned_minutes']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
