{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Nachhaltigkeitsbericht als PDF (Vollaudit 2026-07, N21): Kennzahlen +
     Methodik-/Datenstand-Block, gerendert über die 076-Report-Pipeline
     (DocumentDesignRenderer, Dokumentart Bericht). Keine Konformitäts- oder
     Klimaneutralitätsbehauptung — Schätzwerte sind ausgewiesen. --}}

@extends('reports.pdf.layout')

@section('pdf-title', __('Nachhaltigkeitsbericht'))
@section('pdf-heading', __('Nachhaltigkeitsbericht'))

@section('pdf-meta')
    {{ __('Zeitraum') }}: {{ $from }} – {{ $to }}
@endsection

@php
    $kg = fn($v): string => number_format((float) $v, 3, ',', '.') . ' kg';
@endphp

@section('pdf-table')
    <h2 style="font-size:13px;margin:8px 0 4px;">{{ __('Emissionen je Scope') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Scope') }}</th>
                <th class="num">{{ __('CO₂e') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($aggregate['co2e_by_scope'] as $scope => $value)
                <tr>
                    <td>{{ $scope }}</td>
                    <td class="num">{{ $kg($value) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">{{ __('Keine Daten') }}</td></tr>
            @endforelse
            <tr>
                <td><strong>{{ __('Gesamt') }}</strong></td>
                <td class="num"><strong>{{ $kg($aggregate['co2e_total_kg']) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <h2 style="font-size:13px;margin:12px 0 4px;">{{ __('Aktivitätsdaten') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Aktivität') }}</th>
                <th class="num">{{ __('Menge') }}</th>
                <th>{{ __('Einheit') }}</th>
                <th class="num">{{ __('CO₂e') }}</th>
                <th>{{ __('Faktorquelle') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($aggregate['activities'] as $code => $activity)
                <tr>
                    <td>{{ $code }}</td>
                    <td class="num">{{ number_format((float) $activity['amount'], 3, ',', '.') }}</td>
                    <td>{{ $activity['unit'] }}</td>
                    <td class="num">{{ $activity['co2e_kg'] !== null ? $kg($activity['co2e_kg']) : __('Faktor fehlt') }}</td>
                    <td>{{ $activity['factor_source'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="font-size:13px;margin:12px 0 4px;">{{ __('Datenqualität') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Qualität') }}</th>
                <th class="num">{{ __('Datensätze') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($aggregate['quality_share'] as $quality => $count)
                <tr>
                    <td>{{ $quality }}</td>
                    <td class="num">{{ $count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 style="font-size:13px;margin:12px 0 4px;">{{ __('Methodik & Datenstand') }}</h2>
    <p style="font-size:11px;">
        {{ __('Faktor-Sets') }}: {{ implode(', ', $factorSetNames) !== '' ? implode(', ', $factorSetNames) : '—' }}<br>
        {{ __('Kennzahlen ohne Konformitäts- oder Klimaneutralitätsbehauptung; Schätzwerte sind ausgewiesen.') }}
    </p>
@endsection
