{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : training.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  PDF der Schulungs-Auswertung (Feature 145) — Kompetenznachweis für Audits.
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('training.report.title') . ' – ' . now()->fdate())
@section('pdf-heading', __('training.report.title'))

@section('pdf-meta')
    {{ __('Stand') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('training.report.kpi.assignments') }}</div><div class="value">{{ $report['totals']['assignments'] }}</div></td>
            <td><div class="label">{{ __('training.report.kpi.fulfilled') }}</div><div class="value">{{ $report['totals']['fulfilled'] }}</div></td>
            <td><div class="label">{{ __('training.report.kpi.due') }}</div><div class="value">{{ $report['totals']['due'] }}</div></td>
            <td><div class="label">{{ __('training.report.kpi.overdue') }}</div><div class="value">{{ $report['totals']['overdue'] }}</div></td>
            <td><div class="label">{{ __('training.report.kpi.rate') }}</div><div class="value">{{ $report['totals']['rate'] }} %</div></td>
        </tr>
    </table>

    @if ($report['totals']['assignments'] === 0)
        <p style="text-align:center; padding:20px; color:#888;">{{ __('training.report.empty') }}</p>
    @else
        @foreach ([['by_team', 'byTeam'], ['by_role', 'byRole'], ['by_course', 'byCourse']] as [$labelKey, $dataKey])
            <h3>{{ __('training.report.' . $labelKey) }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('training.report.' . str_replace('by_', '', $labelKey)) }}</th>
                        <th>{{ __('training.report.kpi.assignments') }}</th>
                        <th>{{ __('training.report.kpi.fulfilled') }}</th>
                        <th>{{ __('training.report.kpi.due') }}</th>
                        <th>{{ __('training.report.kpi.overdue') }}</th>
                        <th>{{ __('training.report.rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report[$dataKey] as $group)
                        <tr>
                            <td>{{ $group['label'] }}</td>
                            <td>{{ $group['total'] }}</td>
                            <td>{{ $group['fulfilled'] }}</td>
                            <td>{{ $group['due'] }}</td>
                            <td>{{ $group['overdue'] }}</td>
                            <td>{{ $group['rate'] }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
@endsection
