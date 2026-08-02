@extends('layouts.app')

@section('title', __('Entsorgungsbericht'))
@section('nav-title', __('Entsorgungsbericht'))

@section('content')
<x-index-page :subtitle="__('Entsorgte Mengen abgeschlossener Entsorgungsakten je Kunde, Periode und AVV-Abfallschlüssel — Zeitraum aus dem Kopfbereich.')">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile :label="__('Abgeschlossene Vorgänge')" :value="$jobCount" />
        <x-kpi-tile :label="__('Entsorgte Geräte')" :value="$deviceCount" />
        <x-kpi-tile :label="__('Gesamtgewicht')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totalWeight, 1, withThousandsSeparator: true) . ' kg'" />
        <x-kpi-tile :label="__('Davon gefährlich')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($hazardousWeight, 1, withThousandsSeparator: true) . ' kg'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Mengen je Kunde')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Kunde') }}</th><th class="text-right">{{ __('Vorgänge') }}</th><th class="text-right">{{ __('Geräte') }}</th><th class="text-right">{{ __('Gewicht (kg)') }}</th><th class="text-right">{{ __('Gefährlich (kg)') }}</th></tr></x-slot:head>
                @forelse ($byCustomer as $customer => $row)
                    <tr>
                        <td>{{ $customer }}</td>
                        <td class="text-right font-mono">{{ $row['jobs'] }}</td>
                        <td class="text-right font-mono">{{ $row['devices'] }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['weight'], 1, withThousandsSeparator: true) }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['hazardous_weight'], 1, withThousandsSeparator: true) }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('Keine abgeschlossenen Entsorgungsakten im Zeitraum.')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('Mengen je AVV-Abfallschlüssel')" padding="p-0">
            <x-table bare>
                <x-slot:head><tr><th>{{ __('AVV-Schlüssel') }}</th><th class="text-right">{{ __('Geräte') }}</th><th class="text-right">{{ __('Gewicht (kg)') }}</th></tr></x-slot:head>
                @forelse ($byWasteCode as $code => $row)
                    <tr>
                        <td class="font-mono">{{ $code }} @if ($row['is_hazardous'])<span class="badge badge-error badge-xs align-middle">{{ __('gefährlich') }}</span>@endif</td>
                        <td class="text-right font-mono">{{ $row['devices'] }}</td>
                        <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['weight'], 1, withThousandsSeparator: true) }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" :title="__('Keine abgeschlossenen Entsorgungsakten im Zeitraum.')" compact />
                @endforelse
            </x-table>
        </x-card>
    </div>

    <x-card :title="__('Mengen je Monat')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Monat') }}</th><th class="text-right">{{ __('Geräte') }}</th><th class="text-right">{{ __('Gewicht (kg)') }}</th></tr></x-slot:head>
            @forelse ($byMonth as $month => $row)
                <tr>
                    <td class="font-mono">{{ $month }}</td>
                    <td class="text-right font-mono">{{ $row['devices'] }}</td>
                    <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['weight'], 1, withThousandsSeparator: true) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="3" :title="__('Keine abgeschlossenen Entsorgungsakten im Zeitraum.')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
