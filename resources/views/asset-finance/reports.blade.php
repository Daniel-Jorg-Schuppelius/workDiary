@extends('layouts.app')

@section('title', __('Leasingbericht'))
@section('nav-title', __('Leasingbericht'))

@section('content')
<x-index-page :subtitle="__('Bestand, Restlaufzeiten, Fristen, Kosten und Limit-Überschreitungen — operative Referenzwerte ohne Bilanzierung (W11).')">
    <x-slot:actions>
        <form method="POST" action="{{ route('asset-finance.reports.snapshot') }}">@csrf
            <button type="submit" class="btn btn-sm">{{ __('Snapshot einfrieren') }}</button>
        </form>
    </x-slot:actions>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Verträge gesamt')" :value="$contractCount" />
        <x-kpi-tile :label="__('Laufend')" :value="$openCount" />
        <x-kpi-tile :label="__('Offene Fristen')" :value="$openDeadlines" />
        <x-kpi-tile :label="__('Versäumte Fristen')" :value="$missedDeadlines" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('Soll (Ratenplan)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($plannedTotal, 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Referenziert (Eingangsrechnungen)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($referencedTotal, 2, withThousandsSeparator: true) . ' €'" />
        <x-kpi-tile :label="__('Offen')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($openTotal, 2, withThousandsSeparator: true) . ' €'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Verträge mit Ende in ≤ 6 Monaten (Rückgaberisiken)')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Vertrag') }}</th><th>{{ __('Partner') }}</th><th>{{ __('Ende') }}</th></tr></x-slot:head>
                @forelse ($endingSoon as $row)
                    <tr>
                        <td class="font-mono">{{ $row['number'] }}</td>
                        <td>{{ $row['partner'] }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['ends_on'])->fdate() }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" :title="__('Keine auslaufenden Verträge.')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('Limit-Überschreitungen (Kilometer/Stunden/Tage)')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Vertrag') }}</th><th>{{ __('Limit') }}</th><th class="text-right">{{ __('Überschreitung') }}</th></tr></x-slot:head>
                @forelse ($overruns as $row)
                    <tr>
                        <td class="font-mono">{{ $row['contract'] }}</td>
                        <td>{{ $row['kind'] }}</td>
                        <td class="text-right font-mono text-error">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['overrun'], 2, withThousandsSeparator: true) }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" :title="__('Keine Überschreitungen.')" compact />
                @endforelse
            </x-table>
        </x-card>
    </div>

    <x-card :title="__('Eingefrorene Snapshots (P2)')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Zeitraum') }}</th><th>{{ __('Erstellt') }}</th><th class="text-right">{{ __('Verträge') }}</th><th class="text-right">{{ __('Soll') }}</th></tr></x-slot:head>
            @forelse ($snapshots as $snapshot)
                <tr>
                    <td>{{ $snapshot->period_start->fdate() }} – {{ $snapshot->period_end->fdate() }}</td>
                    <td>{{ $snapshot->created_at?->fdatetime() }}</td>
                    <td class="text-right font-mono">{{ data_get($snapshot->payload, 'contractCount', 0) }}</td>
                    <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) data_get($snapshot->payload, 'plannedTotal', 0), 2, withThousandsSeparator: true) }} €</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Noch keine Snapshots eingefroren.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <p class="text-xs text-base-content/60">{{ $disclaimer }}</p>
</x-index-page>
@endsection
