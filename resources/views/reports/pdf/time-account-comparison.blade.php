{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-account-comparison.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Zeitkonten-Periodenvergleich') . ' – ' . $account->name)
@section('pdf-heading', __('Periodenvergleich') . ' ' . $account->name)

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> –
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ $granularity === 'month' ? __('Monat') : __('Kalenderwoche') }} · {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th class="right">{{ __('Anfangsstand') }}</th>
                @foreach ($periods as $period)
                    <th class="right">{{ $period['label'] }}</th>
                @endforeach
                <th class="right">{{ __('Umsatz') }}</th>
                <th class="right">{{ __('Endstand') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    <td class="right">{{ $account->unit->format($row['opening']) }}</td>
                    @foreach ($periods as $period)
                        <td class="right">{{ ($row['byPeriod'][$period['key']] ?? 0.0) !== 0.0 ? $account->unit->format($row['byPeriod'][$period['key']]) : '–' }}</td>
                    @endforeach
                    <td class="right">{{ $account->unit->format($row['turnover']) }}</td>
                    <td class="right">{{ $account->unit->format($row['closing']) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 4 + count($periods) }}">{{ __('Keine Buchungen im gewählten Zeitraum.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
