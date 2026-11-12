{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Investitionsbericht als PDF (Feature 069, MVP-1606): Budgetauslastung je Akte
     über die Report-Pipeline (DocumentDesignRenderer, Dokumentart Bericht). --}}

@extends('reports.pdf.layout')

@section('pdf-title', __('investment.report.pdf_title'))
@section('pdf-heading', __('investment.report.pdf_title'))

@section('pdf-meta')
    {{ __('investment.report.pdf_meta', ['approvals' => $openApprovals, 'deviations' => $openDeviations]) }}
    @if ($statusLabel !== null) · {{ __('Status') }}: {{ $statusLabel }} @endif
@endsection

@php
    $eur = fn ($v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('pdf-table')
    <table>
        <thead>
            <tr>
                <th>{{ __('Titel') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Kostenstelle') }}</th>
                <th class="num">{{ __('investment.report.approved') }}</th>
                <th class="num">{{ __('investment.report.committed') }}</th>
                <th class="num">{{ __('investment.report.actual') }}</th>
                <th class="num">{{ __('investment.report.remaining') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['case']->title }}</td>
                    <td>{{ $row['case']->status->label() }}</td>
                    <td>{{ $row['case']->costCenterDisplay() ?? '—' }}</td>
                    <td class="num">{{ $eur($row['projection']['approved']) }}</td>
                    <td class="num">{{ $eur($row['projection']['committed']) }}</td>
                    <td class="num">{{ $eur($row['projection']['actual']) }}</td>
                    <td class="num">{{ $eur($row['projection']['remaining']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('Keine Daten') }}</td></tr>
            @endforelse
            <tr>
                <td colspan="3"><strong>{{ __('Gesamt') }}</strong></td>
                <td class="num"><strong>{{ $eur($totals['approved']) }}</strong></td>
                <td class="num"><strong>{{ $eur($totals['committed']) }}</strong></td>
                <td class="num"><strong>{{ $eur($totals['actual']) }}</strong></td>
                <td class="num"><strong>{{ $eur($totals['approved'] - $totals['actual']) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endsection
