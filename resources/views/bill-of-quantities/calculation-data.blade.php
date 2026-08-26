{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : calculation-data.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kalkulationsdaten: :name', ['name' => $bill->name]))
@section('nav-title', __('Kalkulationsdaten'))

@php
    $money = static fn (?float $value): string => $value === null
        ? '—'
        : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">{{ $bill->name }}</div>
            <x-slot:actions>
                <x-icon-btn icon="download" size="sm" show-label
                            :href="route('bill-of-quantities.calculation-data', [$bill, 'export' => 'csv'])">{{ __('CSV') }}</x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" show-label
                            :href="route('bill-of-quantities.calculation-data', [$bill, 'export' => 'xlsx'])">Excel</x-icon-btn>
                <x-icon-btn icon="arrow_back" size="sm" show-label
                            :href="route('bill-of-quantities.show', $bill)">{{ __('Zum Leistungsverzeichnis') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (empty($report['byCostType']))
        <x-empty-state framed icon="calculate"
                       :title="__('Keine Kalkulationsdaten im Leistungsverzeichnis.')"
                       :message="__('Kalkulationsdaten kommen mit einer GAEB-X52-Datei. Ohne Kostenarten im Kopf und Kostenansätze an den Positionen gibt es nichts zu rechnen.')" />
    @else
        {{-- Zuschlagspositionen mit eigenen Ansätzen sind ein Formatverstoß: Der
             Zuschlag rechnet auf andere Positionen, das Geld zählte sonst zweimal. --}}
        @if (! empty($markupWithApproaches))
            <div class="alert alert-warning">
                <x-icon name="warning" />
                <span>{{ __('Zuschlagspositionen mit eigenen Kostenansätzen: :refs. Der Zuschlag rechnet prozentual auf andere Positionen — eigene Ansätze zählen dasselbe Geld ein zweites Mal.', ['refs' => implode(', ', $markupWithApproaches)]) }}</span>
            </div>
        @endif

        <x-card :title="__('Je Kostenart')">
            <p class="mb-3 text-sm text-base-content/70">
                {{ __('EKT sind die Einzelkosten der Teilleistung, GKT der Zuschlag darauf. Der Zuschlagssatz hängt an der Kostenart — ein Betrieb schlägt auf Lohn anders zu als auf Material, aber nicht je Position.') }}
            </p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kostenart') }}</th>
                        <th class="text-right">{{ __('EKT') }}</th>
                        <th class="text-right">{{ __('GKT') }}</th>
                        <th class="text-right">{{ __('Summe') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($report['byCostType'] as $row)
                    <tr>
                        <td><span class="font-mono">{{ $row['key'] }}</span> {{ $row['description'] }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['ekt']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['gkt']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['total']) }}</td>
                    </tr>
                @endforeach
                <tr class="font-medium border-t-2 border-base-300">
                    <td>{{ __('Gesamt') }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['ekt']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['gkt']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['calculated']) }}</td>
                </tr>
            </x-table>
        </x-card>

        <x-card :title="__('Je Position')">
            <p class="mb-3 text-sm text-base-content/70">
                {{ __('Die Differenz ist der eigentliche Befund: Eine Kalkulation, die vom Positionsbetrag abweicht, ist entweder unvollständig übertragen oder bewusst korrigiert worden.') }}
                @if ($report['unpriced'] > 0)
                    {{-- Ohne diesen Hinweis sähe die Gesamtdifferenz nach einem
                         Kalkulationsfehler aus, obwohl nur Preise fehlen. --}}
                    <span class="text-warning">{{ trans_choice('gaeb.calculation.unpriced_hint', $report['unpriced'], ['count' => $report['unpriced']]) }}</span>
                @endif
            </p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Position') }}</th>
                        <th class="text-right">{{ __('EKT') }}</th>
                        <th class="text-right">{{ __('GKT') }}</th>
                        <th class="text-right">{{ __('Kalkuliert') }}</th>
                        <th class="text-right">{{ __('Angeboten') }}</th>
                        <th class="text-right">{{ __('Differenz') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($report['items'] as $row)
                    <tr>
                        <td>
                            <span class="font-mono">{{ $row['item']->reference_no }}</span>
                            <span class="text-base-content/70">{{ \Illuminate\Support\Str::limit((string) $row['item']->short_text, 60) }}</span>
                        </td>
                        <td class="text-right tabular-nums">{{ $money($row['ekt']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['gkt']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['calculated']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['offered']) }}</td>
                        <td class="text-right tabular-nums @if ($row['delta'] !== null && abs($row['delta']) >= 0.01) text-warning font-medium @endif">{{ $money($row['delta']) }}</td>
                    </tr>
                @endforeach
                <tr class="font-medium border-t-2 border-base-300">
                    <td>{{ __('Gesamt') }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['ekt']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['gkt']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['calculated']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['offered']) }}</td>
                    <td class="text-right tabular-nums @if (abs($report['delta']) >= 0.01) text-warning @endif">{{ $money($report['delta']) }}</td>
                </tr>
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
