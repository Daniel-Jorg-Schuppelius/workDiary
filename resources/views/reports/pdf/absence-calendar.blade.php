{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : absence-calendar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Fehlzeiträume') . ' ' . $year)
@section('pdf-heading', __('Fehlzeiträume') . ' ' . $year)

@section('pdf-meta')
    {{ __('Jahr') }}: <strong>{{ $year }}</strong> · {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @forelse ($rows as $r)
        <h2>{{ $r['user']->name }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Von') }}</th>
                    <th>{{ __('Bis') }}</th>
                    <th>{{ __('Fehlgrund') }}</th>
                    <th class="right">{{ __('Kalendertage') }}</th>
                    <th class="right">{{ __('Effektive Arbeitstage') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($r['items'] as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($item['from'])->fdate() }}</td>
                        <td>{{ \Carbon\Carbon::parse($item['to'])->fdate() }}</td>
                        <td>{{ $item['label'] }}</td>
                        <td class="right">{{ $item['calendar'] }}</td>
                        <td class="right">{{ number_format($item['effective'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>{{ __('Keine Fehlzeiträume im gewählten Jahr.') }}</p>
    @endforelse
@endsection
