{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- DSFA-Bericht (Art. 35 DSGVO); Branding via reports.pdf.layout (D3). --}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Datenschutz-Folgenabschätzung') . ' — ' . $activity->name)
@section('pdf-heading', __('Datenschutz-Folgenabschätzung (Art. 35 DSGVO)'))

@push('pdf-styles')
<style>
    body { line-height: 1.45; }
    h2 { margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
    .content { white-space: pre-wrap; }
    .step-meta { color: #6b7280; font-size: 9px; margin-top: 2px; }
    table { margin-top: 8px; }
</style>
@endpush

@section('pdf-meta')
    {{ __('Verarbeitungstätigkeit') }}: {{ $activity->name }} ·
    {{ __('Ergebnis') }}: {{ $dpia->outcome->label() }}
    @if ($dpia->residual_risk) · {{ __('Restrisiko') }}: {{ $dpia->residual_risk }} @endif
    @if ($dpia->assessed_at) · {{ __('Bewertet am') }}: {{ $dpia->assessed_at->format('d.m.Y') }} @endif
@endsection

@section('pdf-table')
    @foreach ($dpia->steps as $step)
        <h2>{{ $loop->iteration }}. {{ $step->label() }}</h2>
        <div class="content">{{ $step->content ?: '—' }}</div>
        <div class="step-meta">
            {{ $step->isDone() ? __('Abgeschlossen') : __('Offen') }}
            @if ($step->completed_at) · {{ $step->completed_at->format('d.m.Y H:i') }} @endif
            @if ($step->completedBy) · {{ $step->completedBy->name }} @endif
        </div>
    @endforeach

    <h2>{{ __('Zusammenfassung') }}</h2>
    <table>
        <tr><th>{{ __('Notwendigkeit & Verhältnismäßigkeit') }}</th><td class="content">{{ $dpia->necessity ?: '—' }}</td></tr>
        <tr><th>{{ __('Risiken für Betroffene') }}</th><td class="content">{{ $dpia->risks ?: '—' }}</td></tr>
        <tr><th>{{ __('Abhilfemaßnahmen') }}</th><td class="content">{{ $dpia->mitigations ?: '—' }}</td></tr>
    </table>
@endsection
