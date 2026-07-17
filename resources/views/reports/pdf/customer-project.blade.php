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
                    $hC = intdiv((int) $row['minutes'], 60);
                    $mC = (int) $row['minutes'] % 60;
                    $customerName = $row['customer'] ? $row['customer']->name : '(Ohne Kunde)';
                @endphp
                <tr class="customer-row">
                    <td>{{ $customerName }}</td>
                    <td></td>
                    <td class="right">{{ $hC }}:{{ str_pad((string) $mC, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</td>
                </tr>
                @foreach ($row['projects'] as $entry)
                    @php
                        $hp = intdiv((int) $entry['minutes'], 60);
                        $mp = (int) $entry['minutes'] % 60;
                    @endphp
                    <tr class="project-row">
                        <td>{{ $entry['project']->name }}@if ($entry['project']->foreignCustomer) · {{ $entry['project']->foreignCustomer->name }}@endif</td>
                        <td>{{ $entry['project']->number }}</td>
                        <td class="right">{{ $hp }}:{{ str_pad((string) $mp, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="right">{{ number_format((float) $entry['rate'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            @endforeach
            @php
                $hT = intdiv((int) $totalMinutes, 60);
                $mT = (int) $totalMinutes % 60;
            @endphp
            <tr class="totals">
                <td>Gesamt</td>
                <td></td>
                <td class="right">{{ $hT }}:{{ str_pad((string) $mT, 2, '0', STR_PAD_LEFT) }}</td>
                <td class="right">{{ number_format((float) $totalRate, 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>
@endsection
