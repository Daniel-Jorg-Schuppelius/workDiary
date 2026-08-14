@extends('layouts.app')

@section('title', __('Qualitätsbericht Reklamationen'))
@section('nav-title', __('Qualitätsbericht'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">{{ __('Quote, Ursachen, Produkte, Lieferanten, Kosten, Dauer und Wiederholfehler — Zeitraum nach Meldedatum.') }}</div>
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" :href="route('claims.reports.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => 'csv'])" show-label>{{ __('CSV') }}</x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" :href="route('claims.reports.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => 'xlsx'])" show-label>Excel</x-icon-btn>
                <form method="POST" action="{{ route('claims.reports.snapshot', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
                    @csrf
                    <x-icon-btn icon="ac_unit" size="sm" type="submit" show-label>{{ __('Stand einfrieren') }}</x-icon-btn>
                </form>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('claims.reports.index')" :reset="route('claims.reports.index')">
        <x-date-range :from="$from->toDateString()" :to="$to->toDateString()" from-name="from" to-name="to" />
    </x-filter-bar>

    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <x-kpi-tile :label="__('Fälle gesamt')" :value="$data['total']" />
        <x-kpi-tile :label="__('Offen')" :value="$data['open']" />
        <x-kpi-tile :label="__('Abgeschlossen')" :value="$data['closed']" />
        <x-kpi-tile :label="__('Überfällig')" :value="$data['overdue']" />
        <x-kpi-tile :label="__('Ø Dauer (Tage)')" :value="$data['avg_duration_days'] ?? '—'" />
        <x-kpi-tile :label="__('Kosten (ausgeführt)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $data['cost_total'], 2, withThousandsSeparator: true) . ' €'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach (['by_cause' => __('Nach Ursache'), 'by_defect' => __('Nach Mangelart'), 'by_article' => __('Nach Artikel'), 'by_supplier' => __('Nach Lieferant')] as $key => $label)
            <x-card :title="$label">
                @if ($data[$key] === [])
                    <p class="text-sm text-base-content/60">{{ __('Keine Daten im Zeitraum.') }}</p>
                @else
                    <x-table bare>
                        @foreach ($data[$key] as $name => $count)
                            <tr>
                                <td><a class="link" href="{{ route('claims.index') }}">{{ $name }}</a></td>
                                <td class="text-right font-mono">{{ $count }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        @endforeach
    </div>

    <x-card :title="__('Wiederholfehler (Artikel × Ursache mehrfach)')">
        @if ($data['repeats'] === [])
            <p class="text-sm text-base-content/60">{{ __('Keine Wiederholfehler im Zeitraum.') }}</p>
        @else
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('Artikel') }}</th><th>{{ __('Ursache') }}</th><th class="text-right">{{ __('Fälle') }}</th></tr>
                </x-slot:head>
                @foreach ($data['repeats'] as $repeat)
                    <tr>
                        <td>{{ $repeat['article'] }}</td>
                        <td>{{ $repeat['cause'] }}</td>
                        <td class="text-right font-mono">{{ $repeat['count'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card :title="__('Eingefrorene Berichtsstände')">
        @if ($snapshots->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Noch keine Snapshots.') }}</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($snapshots as $snapshot)
                    <li>{{ $snapshot->period_start->fdate() }} – {{ $snapshot->period_end->fdate() }} ({{ $snapshot->created_at->fdatetime() }}): {{ __(':total Fälle, :cost € Kosten', ['total' => $snapshot->payload['total'] ?? 0, 'cost' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) ($snapshot->payload['cost_total'] ?? 0), 2, withThousandsSeparator: true)]) }}</li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-page-shell>
@endsection
