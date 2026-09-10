{{--
  Created on   : Thu Sep 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report_pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Margenbericht als PDF (Feature 152, MVP-765) über die Report-Pipeline von
  Feature 002 (reports.pdf.layout, DocumentDesignRenderer → pdf-toolkit):
  beide Blöcke als Tabellen, keine Diagramme.
--}}
@extends('reports.pdf.layout')

@php
    $money = static fn(float $v, \CommonToolkit\Enums\CurrencyCode $c): string => \CommonToolkit\ValueObjects\Money::ofFloat($v, $c, 2)->format();
@endphp

@section('pdf-title', __('resale.margin.pdf_title'))
@section('pdf-heading', __('resale.margin.pdf_title'))
@section('pdf-meta'){{ __('resale.margin.subtitle', ['from' => $from->fdate(), 'to' => $to->fdate()]) }} · {{ __('resale.report.subtitle', ['date' => $today->fdate()]) }}@endsection

@section('pdf-table')
    @if ($report['mixed'])
        <p class="small">{{ __('resale.margin.mixed_currencies', ['list' => implode(', ', array_map(static fn($c) => $c->value, $report['currencies']))]) }}</p>
    @endif
    @foreach ([['title' => __('resale.report.by_product'), 'rows' => $report['by_product'], 'first' => __('resale.field.article')], ['title' => __('resale.report.by_recipient'), 'rows' => $report['by_recipient'], 'first' => __('resale.field.billed_to')]] as $block)
        <h2>{{ $block['title'] }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ $block['first'] }}</th>
                    <th>{{ __('resale.margin.currency') }}</th>
                    <th class="num">{{ __('resale.report.periods') }}</th>
                    <th class="num">{{ __('resale.report.open') }}</th>
                    <th class="num">{{ __('resale.report.expected_sale') }}</th>
                    <th class="num">{{ __('resale.report.billed') }}</th>
                    <th class="num">{{ __('resale.report.expected_purchase') }}</th>
                    <th class="num">{{ __('resale.report.actual_purchase') }}</th>
                    <th class="num">{{ __('resale.report.margin') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($block['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ $row['currency']->value }}</td>
                        <td class="num">{{ $row['periods'] }}</td>
                        <td class="num">{{ $row['open'] }}</td>
                        <td class="num">{{ $money($row['expected_sale'], $row['currency']) }}</td>
                        <td class="num">{{ $money($row['billed'], $row['currency']) }}</td>
                        <td class="num">{{ $money($row['expected_purchase'], $row['currency']) }}</td>
                        <td class="num">{{ $row['with_actual'] > 0 ? $money($row['actual_purchase'], $row['currency']) : '–' }}@if ($row['with_actual'] > 0 && $row['with_actual'] < $row['periods']) ({{ $row['with_actual'] }}/{{ $row['periods'] }})@endif</td>
                        <td class="num {{ $row['margin'] < 0 ? 'neg' : 'pos' }}">{{ $money($row['margin'], $row['currency']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">{{ __('resale.report.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
    <p class="small">{{ __('resale.report.hint') }}</p>
@endsection
