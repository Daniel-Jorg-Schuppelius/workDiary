{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : hoai-cost-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $money = static fn (?float $value): string => $value === null
        ? '—'
        : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true) . ' €';
    $stages = \App\Models\Costing\CostEstimate::STAGES;
@endphp

<x-pdf-layout pdf-type="report" :pdf-title="__('Kostenermittlung: :name', ['name' => $project->name])">
    <x-slot:documentMeta>
        {{ __('Kostenermittlung nach DIN 276 / HOAI') }} · {{ $project->name }}
        · {{ __('Erzeugt am :date', ['date' => now()->format('d.m.Y')]) }}
    </x-slot:documentMeta>

    <h1 style="font-size:14pt;margin:0 0 4mm 0">{{ __('Kostenermittlung: :name', ['name' => $project->name]) }}</h1>

    @if (empty($report['rows']))
        <p>{{ __('Für dieses Projekt liegt keine Kostenermittlung vor.') }}</p>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:8pt">
            <thead>
                <tr>
                    <th style="text-align:left;border-bottom:1px solid #999;padding:1mm">{{ __('Kostengruppe') }}</th>
                    @foreach ($stages as $stage)
                        <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('costing.stage.' . $stage) }}</th>
                    @endforeach
                    <th style="text-align:right;border-bottom:1px solid #999;padding:1mm">{{ __('Abweichung') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td style="padding:1mm">{{ $row['code'] !== '' ? $row['code'] . ' ' : '' }}{{ $row['label'] }}</td>
                        @foreach ($stages as $stage)
                            <td style="text-align:right;padding:1mm">{{ $money($row['amounts'][$stage]) }}</td>
                        @endforeach
                        <td style="text-align:right;padding:1mm">{{ $money($row['delta']) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="border-top:1px solid #999;padding:1mm;font-weight:bold">{{ __('Gesamt') }}</td>
                    @foreach ($stages as $stage)
                        <td style="border-top:1px solid #999;text-align:right;padding:1mm;font-weight:bold">{{ $money($report['totals'][$stage]) }}</td>
                    @endforeach
                    <td style="border-top:1px solid #999;text-align:right;padding:1mm;font-weight:bold">{{ $money($report['delta']) }}</td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:3mm;font-size:7.5pt;color:#555">
            {{ __('Die Abweichung vergleicht die erste mit der letzten vorhandenen Stufe. Fehlt eine Stufe, bleibt ihre Spalte leer — sie wurde nicht ermittelt.') }}
        </p>
    @endif
</x-pdf-layout>
