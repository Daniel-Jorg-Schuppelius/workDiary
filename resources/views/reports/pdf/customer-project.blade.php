{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-project.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Kunden & Projekte – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Kunden & Projekte'))

@push('pdf-styles')
<style>
    .customer-row td { background: #eef; font-weight: bold; }
    .project-row td { padding-left: 16px; }
</style>
@endpush

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Kunde / Projekt') }}</th>
                <th style="width: 14%">{{ __('Projekt-Nr.') }}</th>
                <th class="right" style="width: 14%">Stunden</th>
                <th class="right" style="width: 16%">{{ __('Erlös') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bucket as $row)
                @php
                    $customerName = $row['customer'] ? $row['customer']->name : '(Ohne Kunde)';
                @endphp
                <tr class="customer-row">
                    <td>{{ $customerName }}</td>
                    <td></td>
                    <td class="right">{{ \App\Support\Formats::duration((int) $row['minutes'], 'clock', withUnit: false) }}</td>
                    <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                </tr>
                @foreach ($row['projects'] as $entry)
                    <tr class="project-row">
                        <td>{{ $entry['project']->name }}@if ($entry['project']->foreignCustomer) · {{ $entry['project']->foreignCustomer->name }}@endif</td>
                        <td>{{ $entry['project']->number }}</td>
                        <td class="right">{{ \App\Support\Formats::duration((int) $entry['minutes'], 'clock', withUnit: false) }}</td>
                        <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $entry['rate'], 2, withThousandsSeparator: true) }} €</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="totals">
                <td>Gesamt</td>
                <td></td>
                <td class="right">{{ \App\Support\Formats::duration((int) $totalMinutes, 'clock', withUnit: false) }}</td>
                <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $totalRate, 2, withThousandsSeparator: true) }} €</td>
            </tr>
        </tbody>
    </table>
@endsection
