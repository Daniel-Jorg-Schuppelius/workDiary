@extends('layouts.app')

@section('title', __('Investitions-Auswertung'))
@section('nav-title', __('Investitions-Auswertung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('Investitions-Auswertung')">
            <div class="text-sm text-base-content/70">{{ __('Pipeline, Budgetauslastung und offene Entscheidungen.') }}</div>
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" :href="route('investments.report', ['export' => 'csv'])" show-label>{{ __('CSV') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-kpi-tile :label="__('Genehmigt gesamt')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['approved'], 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Gebunden')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['committed'], 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Ist-Kosten')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['actual'], 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Offene Freigaben / Abweichungen')" :value="$openApprovals . ' / ' . $openDeviations" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Pipeline') }}</h3>
            @if (empty($pipeline))
                <x-empty-state icon="trending_up" :title="__('Keine Investitionen.')" />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($pipeline as $status => $count)
                        <li class="flex justify-between gap-4"><span>{{ __("values.$status") }}</span><span class="tabular-nums">{{ $count }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Budgetauslastung je Akte') }}</h3>
            @if (empty($rows))
                <x-empty-state icon="request_quote" :title="__('Keine genehmigten Budgets.')" />
            @else
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Akte') }}</th>
                                <th class="text-right">{{ __('Genehmigt') }}</th>
                                <th class="text-right">{{ __('Ist') }}</th>
                                <th class="text-right">{{ __('Rest') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($rows as $row)
                                <tr @class(['text-error' => $row['projection']['remaining'] !== null && $row['projection']['remaining'] < 0])>
                                    <td><a class="link" href="{{ route('investments.show', $row['case']) }}">{{ $row['case']->title }}</a></td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['projection']['approved'], 2, withThousandsSeparator: true) }} €</td>
                                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['projection']['actual'], 2, withThousandsSeparator: true) }} €</td>
                                    <td class="text-right tabular-nums">{{ $row['projection']['remaining'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['projection']['remaining'], 2, withThousandsSeparator: true) . ' €' : '—' }}</td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</x-page-shell>
@endsection
