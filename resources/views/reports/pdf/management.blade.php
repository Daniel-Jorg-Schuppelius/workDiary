{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : management.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Leitungsbericht als PDF (Feature 184). Erwartet: $kpis (Bereich → list<ManagementKpi>), $warnings (list<EarlyWarning>), $from, $to.
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('reporting.management.title') . ' – ' . $from->fdate() . ' ' . __('bis') . ' ' . $to->fdate())
@section('pdf-heading', __('reporting.management.title'))

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ $from->fdate() }}</strong> {{ __('bis') }} <strong>{{ $to->fdate() }}</strong> ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @forelse ($kpis as $group => $groupKpis)
        <h2>{{ __('reporting.management.group.' . $group) }}</h2>
        <table class="data">
            @foreach ($groupKpis as $kpi)
                <tr>
                    <td>{{ $kpi->label }}</td>
                    <td class="num"><strong>{{ $kpi->value }}</strong></td>
                    <td>{{ $kpi->hint }}</td>
                </tr>
            @endforeach
        </table>
    @empty
        <p>{{ __('reporting.management.no_kpis') }}</p>
    @endforelse

    @if ($warnings !== [])
        <h2>{{ __('reporting.management.problems') }}</h2>
        <table class="data">
            @foreach ($warnings as $warning)
                <tr>
                    <td>{{ __('reporting.management.kind.' . $warning->kind) }}</td>
                    <td>{{ __($warning->title, $warning->params) }}</td>
                    <td>{{ __($warning->recommendation) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
