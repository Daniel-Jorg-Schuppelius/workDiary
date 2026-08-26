{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : product-revenue.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Umsatz je Produkt')
@section('pdf-heading', 'Umsatz je Produkt')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('Nettoumsatz gesamt') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($total, 2, withThousandsSeparator: true) }} € ·
        {{ __('Artikel mit Umsatz') }}: {{ $articleCount }} ·
        {{ __('ohne Artikelbezug') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($withoutArticle, 2, withThousandsSeparator: true) }} €
    </p>

    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ __('Artikelnummer') }}</th>
                <th>{{ __('Artikel') }}</th>
                <th class="num">{{ __('Menge') }}</th>
                <th>{{ __('Einheit') }}</th>
                <th class="num">{{ __('Nettoumsatz') }} (€)</th>
                <th class="num">{{ __('Anteil') }} (%)</th>
                <th class="num">{{ __('Rechnungen') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['number'] ?? '–' }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['quantity'], 2, withThousandsSeparator: true) }}</td>
                    <td>{{ $row['unit'] ?? '–' }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['net'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['share'], 1) : '–' }}</td>
                    <td class="num">{{ $row['invoices'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="small">{{ __('Nur lokal ausgestellte Rechnungen — gespiegelte Buchhaltungsbelege tragen keine Positionen.') }}</p>
@endsection
