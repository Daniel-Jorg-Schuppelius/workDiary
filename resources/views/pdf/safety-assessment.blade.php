{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : safety-assessment.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var \App\Models\Safety\HazardAssessment $assessment */
    /** @var \Illuminate\Support\Carbon $generatedAt */
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('safety.register.pdf.assessment_title') }} {{ $assessment->displayNo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 3mm 0; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .meta th { text-align: left; width: 45mm; padding: 1mm 2mm; background: #f3f3f3; }
        .meta td { padding: 1mm 2mm; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th, table.rows td { border: 1px solid #ccc; padding: 1.5mm; vertical-align: top; font-size: 9pt; }
        table.rows th { background: #eee; text-align: left; }
        .center { text-align: center; }
        .muted { color: #555; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>{{ __('safety.register.pdf.assessment_title') }} {{ $assessment->displayNo() }}</h1>
    <table class="meta">
        <tr><th>{{ __('safety.register.field.area') }}</th><td>{{ $assessment->area }}</td></tr>
        @if ($assessment->activity)
            <tr><th>{{ __('safety.register.field.activity') }}</th><td>{{ $assessment->activity }}</td></tr>
        @endif
        @if ($assessment->description)
            <tr><th>{{ __('safety.register.field.description') }}</th><td>{{ $assessment->description }}</td></tr>
        @endif
        <tr><th>{{ __('safety.register.field.status') }}</th><td>{{ $assessment->status->label() }}</td></tr>
        @if ($assessment->approved_at)
            <tr><th>{{ __('safety.register.field.approved_by') }}</th><td>{{ $assessment->approvedBy?->name ?? '–' }} · {{ $assessment->approved_at->orgTz()->format('d.m.Y H:i') }}</td></tr>
        @endif
        <tr><th>{{ __('safety.register.field.review_due_on') }}</th><td>{{ $assessment->review_due_on?->fdate() ?? '–' }}</td></tr>
        @if ($assessment->supersedes)
            <tr><th>{{ __('safety.register.field.supersedes') }}</th><td>{{ $assessment->supersedes->displayNo() }}</td></tr>
        @endif
    </table>

    <table class="rows">
        <thead>
            <tr>
                <th>{{ __('safety.register.field.position') }}</th>
                <th>{{ __('safety.register.field.hazard') }}</th>
                <th class="center">{{ __('safety.register.pdf.severity_short') }}</th>
                <th class="center">{{ __('safety.register.pdf.likelihood_short') }}</th>
                <th class="center">{{ __('safety.register.field.risk_before') }}</th>
                <th>{{ __('safety.register.field.measure') }}</th>
                <th class="center">{{ __('safety.register.pdf.severity_short') }}</th>
                <th class="center">{{ __('safety.register.pdf.likelihood_short') }}</th>
                <th class="center">{{ __('safety.register.field.risk_after') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assessment->items as $item)
                <tr>
                    <td>{{ $item->position }}</td>
                    <td>{{ $item->hazard }}</td>
                    <td class="center">{{ $item->severity_before }}</td>
                    <td class="center">{{ $item->likelihood_before }}</td>
                    <td class="center">{{ $item->risk_before }}</td>
                    <td>{{ $item->measure ?? '–' }}</td>
                    <td class="center">{{ $item->severity_after ?? '–' }}</td>
                    <td class="center">{{ $item->likelihood_after ?? '–' }}</td>
                    <td class="center">{{ $item->risk_after ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted">{{ __('safety.register.pdf.legend') }} · {{ __('safety.register.pdf.generated', ['date' => $generatedAt->orgTz()->format('d.m.Y H:i')]) }}</p>
</body>
</html>
