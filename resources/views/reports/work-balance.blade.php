@extends('layouts.app')

@section('title', __('Arbeitsbilanz'))
@section('nav-title', __('Arbeitsbilanz') . ' — ' . $label)

@php
    $fmt = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $m = abs($minutes);
        return $sign . sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    };
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Soll-Ist-Vergleich von Anwesenheit, erfasster Zeit und Saldo für :user.', ['user' => $user->name])">
                <x-slot:actions>
                    <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                                :href="route('reports.work-balance', array_merge(request()->query(), $standardFilters->toQueryParams(), ['export' => 'pdf']))"
                                show-label>PDF</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if ($isAdmin)
            <x-filter-bar :action="route('reports.work-balance')" :reset="route('reports.work-balance')">
                {{-- Zeitraum-Spezialparameter (year/month bzw. from/to) beim Umfiltern erhalten. --}}
                @foreach (request()->except(['user', 'team', 'export']) as $k => $v)
                    @continue(! is_scalar($v))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                @include('reports._standard_filters', ['idPrefix' => 'work-balance'])
            </x-filter-bar>
        @endif

        <div class="grid gap-3 xl:grid-cols-2">
            <x-charts.bar :title="$dailySeriesLabel" unit="h" :series="$dailySeries" :median="$dailyMedian" :y2-label="__('Soll')" :x-label="__('Zeitraum')" :y-label="__('Ist')" />
            <x-charts.bar :title="__('Ist- und Soll-Stunden je Monat')" unit="h" :series="$monthlySeries" :median="$monthlyMedian" :y2-label="__('Soll')" :x-label="__('Monat')" :y-label="__('Ist')" />
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-kpi-tile :label="__('Soll')" :value="$fmt($period->targetMinutes) . ' h'" />
            <x-kpi-tile :label="__('Anwesenheit')" :value="$fmt($period->attendanceMinutes) . ' h'" />
            <x-kpi-tile :label="__('Erfasst')" :value="$fmt($period->trackedMinutes) . ' h'" />
            <x-kpi-tile :label="__('Unverteilt')" :value="$fmt($period->untrackedMinutes) . ' h'" />
            <x-kpi-tile :label="__('Saldo')" :value="$fmt($period->balanceMinutes) . ' h'"
                        :tone="$period->balanceMinutes >= 0 ? 'success' : 'error'" />
        </div>

        @if (! empty($period->byActivity))
            <x-card>
                <div class="mb-2 text-xs uppercase tracking-wider text-base-content/60">{{ __('Verteilung nach Tätigkeit') }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($period->byActivity as $type => $minutes)
                        <span class="badge badge-outline gap-2 px-3 py-3">
                            <strong>{{ \App\Models\TimeEntry::activityLabel($type) }}</strong>
                            <span>{{ $fmt((int) $minutes) }} h</span>
                        </span>
                    @endforeach
                </div>
            </x-card>
        @endif

        <x-table table-sort="client" :zebra="false">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Soll') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Anwesenheit') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Pause') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Erfasst') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Unverteilt') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Saldo') }}</x-table.th>
                </tr>
            </x-slot:head>
            <x-slot:foot>
                <tr class="font-semibold">
                    <td>{{ __('Summe') }}</td>
                    <td class="text-right">{{ $fmt($period->targetMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->attendanceMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->breakMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->trackedMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->untrackedMinutes) }}</td>
                    <td class="text-right {{ $period->balanceMinutes >= 0 ? 'text-success' : 'text-error' }}">
                        {{ $fmt($period->balanceMinutes) }}
                    </td>
                </tr>
            </x-slot:foot>
            @foreach ($period->days as $day)
                @if ($day->targetMinutes === 0 && $day->attendanceMinutes === 0 && $day->trackedMinutes === 0)
                    @continue
                @endif
                <tr>
                    <td class="font-mono" data-sort-value="{{ \Carbon\Carbon::parse($day->date)->format('Y-m-d') }}">{{ \Carbon\Carbon::parse($day->date)->format('D, d.m.Y') }}</td>
                    <td class="text-right" data-sort-value="{{ (int) $day->targetMinutes }}">{{ $fmt($day->targetMinutes) }}</td>
                    <td class="text-right" data-sort-value="{{ (int) $day->attendanceMinutes }}">{{ $fmt($day->attendanceMinutes) }}</td>
                    <td class="text-right text-base-content/60" data-sort-value="{{ (int) $day->breakMinutes }}">{{ $fmt($day->breakMinutes) }}</td>
                    <td class="text-right" data-sort-value="{{ (int) $day->trackedMinutes }}">{{ $fmt($day->trackedMinutes) }}</td>
                    <td class="text-right text-warning" data-sort-value="{{ (int) $day->untrackedMinutes }}">{{ $fmt($day->untrackedMinutes) }}</td>
                    <td class="text-right {{ $day->balanceMinutes >= 0 ? 'text-success' : 'text-error' }}" data-sort-value="{{ (int) $day->balanceMinutes }}">
                        {{ $fmt($day->balanceMinutes) }}
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-page-shell>
@endsection
