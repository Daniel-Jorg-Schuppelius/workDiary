@extends('layouts.app')

@section('title', __('Bewerbungen & Ausschreibungen'))
@section('nav-title', __('Bewerbungs-Auswertung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">{{ __('Zeitraum: :from – :to · nur aggregierte Kennzahlen, keine Bewerberdetails.', ['from' => $from, 'to' => $to]) }}</div>
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" :href="route('applications.report', array_merge($standardFilters->toQueryParams(), ['export' => 'csv']))" show-label>{{ __('CSV') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('applications.report')" :reset="route('applications.report')">
        @include('reports._standard_filters', ['idPrefix' => 'applications', 'statusOptions' => $statusOptions, 'statusLabel' => __('Bewerbungsstatus')])
    </x-filter-bar>

    @if ($recruiting !== null)
        <div class="grid gap-3 xl:grid-cols-2">
            <x-charts.line :title="__('Bewerbungseingang je Monat')" :unit="__('Bewerbungen')" :series="$monthlySeries" :x-label="__('Monat')" :y-label="__('Bewerbungen')" />
            <x-charts.bar-h :title="__('Bewerber-Funnel je Workflow-Stufe')" :unit="__('Bewerbungen')" :series="$funnelSeries" :x-label="__('Workflow-Stufe')" :y-label="__('Anzahl')" />
        </div>
    @endif

    @if ($tenders !== null)
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Ausschreibungs-Pipeline') }}</h3>
                @if (empty($tenders['pipeline']))
                    <x-empty-state icon="gavel" :title="__('Keine Akten im Zeitraum.')" />
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr><th>{{ __('Status') }}</th><th class="text-right">{{ __('Anzahl') }}</th><th class="text-right">{{ __('Wertpotenzial') }}</th></tr>
                        </x-slot:head>
                        @foreach ($tenders['pipeline'] as $status => $row)
                            <tr>
                                <td>{{ __("values.$status") }}</td>
                                <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                                <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['value'], 2, withThousandsSeparator: true) }} €</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
                <p class="mt-2 text-sm text-base-content/70">
                    {{ __('Trefferquote: :rate', ['rate' => $tenders['win_rate'] !== null ? $tenders['win_rate'] . ' %' : '—']) }}
                    · {{ __('Fristen ≤ 14 Tage: :count', ['count' => $tenders['upcoming']]) }}
                </p>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Verlustgründe') }}</h3>
                @if (empty($tenders['loss_reasons']))
                    <x-empty-state icon="thumb_down" :title="__('Keine Verluste im Zeitraum.')" />
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($tenders['loss_reasons'] as $reason => $count)
                            <li class="flex justify-between gap-4"><span>{{ $reason }}</span><span class="tabular-nums">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif

    @if ($recruiting !== null)
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Bewerber-Pipeline') }}</h3>
                @if (empty($recruiting['pipeline']))
                    <x-empty-state icon="person_search" :title="__('Keine Bewerbungen im Zeitraum.')" />
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($recruiting['pipeline'] as $status => $count)
                            <li class="flex justify-between gap-4"><span>{{ __("values.$status") }}</span><span class="tabular-nums">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-2 text-sm text-base-content/70">{{ __('Ø Tage bis Zusage: :days', ['days' => $recruiting['avg_days_to_accept'] ?? '—']) }}</p>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Quellkanäle') }}</h3>
                @if (empty($recruiting['sources']))
                    <x-empty-state icon="campaign" :title="__('Keine Daten.')" />
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($recruiting['sources'] as $source => $count)
                            <li class="flex justify-between gap-4"><span>{{ __("values.$source") }}</span><span class="tabular-nums">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif

    @if ($contracts !== null)
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Vertragsverhandlungen') }}</h3>
            <x-detail-grid>
                <x-detail-grid.row :label="__('Offen')">{{ $contracts['open'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Offene Blocker-Punkte')">{{ $contracts['open_blockers'] }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Fällig ≤ 14 Tage')">{{ $contracts['due_soon'] }}</x-detail-grid.row>
            </x-detail-grid>
        </x-card>
    @endif
</x-page-shell>
@endsection
