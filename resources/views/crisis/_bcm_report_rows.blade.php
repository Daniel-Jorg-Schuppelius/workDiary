{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bcm_report_rows.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kennzahlen der BCM-Auswertung (MVP-944), gemeinsam für Seite und PDF. Erwartet: $report --}}
{{-- raw-table-ok: Kennzahlen-Matrix, die das PDF ohne Komponenten mitnutzt. --}}
<table>
    <tr><th>{{ __('crisis.bcm_report.row.exercises', ['date' => $report['period_from']->format('d.m.Y')]) }}</th><td>{{ $report['exercises'] }}</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.effectiveness') }}</th><td>{{ collect($report['effectiveness'])->map(fn ($n, $k) => __('crisis.bcm_report.effectiveness.' . $k) . ': ' . $n)->implode(' · ') ?: '—' }}</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.exercises_due') }}</th><td>{{ $report['exercises_due'] }}</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.actions_open') }}</th><td>{{ $report['actions_open'] }} ({{ __('crisis.bcm_report.overdue', ['count' => $report['actions_overdue']]) }})</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.reviews') }}</th><td>{{ $report['cases_ended'] - $report['cases_without_review'] }} / {{ $report['cases_ended'] }}</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.processes') }}</th><td>{{ $report['processes'] }} ({{ collect($report['by_criticality'])->map(fn ($n, $k) => __('crisis.bia.criticality.' . $k) . ': ' . $n)->implode(' · ') ?: '—' }})</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.without_rto') }}</th><td>{{ $report['processes_without_rto'] }}</td></tr>
    <tr><th>{{ __('crisis.bcm_report.row.review_due') }}</th><td>{{ $report['processes_review_due'] }}</td></tr>
</table>
