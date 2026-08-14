{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-retention.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Kundenbindung')
@section('pdf-heading', 'Kundenbindung')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('Wiederkehrquote') }}: {{ $kpis['returningRate'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kpis['returningRate'], 1) . ' %' : '–' }} ·
        {{ __('Aktive Kunden (Ende)') }}: {{ $kpis['endActive'] }}
    </p>

    @include('reports.pdf.charts._chart')

    <h2>{{ __('Kohorten-Retention (Anteil aktiver Kunden je Jahr)') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Erstleistungsjahr') }}</th>
                <th class="num">{{ __('Kunden') }}</th>
                @foreach (range(0, count($cohorts['years']) - 1) as $offset)
                    <th class="num">+{{ $offset }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($cohorts['rows'] as $row)
                <tr>
                    <td>{{ $row['year'] }}</td>
                    <td class="num">{{ $row['size'] }}</td>
                    @foreach ($row['cells'] as $cell)
                        <td class="num">{{ $cell === null ? '–' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($cell, 1) . ' %' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
