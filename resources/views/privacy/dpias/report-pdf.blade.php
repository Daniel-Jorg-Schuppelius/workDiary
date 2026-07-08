<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ __('Datenschutz-Folgenabschätzung') }} — {{ $activity->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111827; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 6px; }
        h2 { font-size: 12pt; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .meta { color: #6b7280; margin-bottom: 14px; }
        .content { white-space: pre-wrap; }
        .step-meta { color: #6b7280; font-size: 8pt; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; font-size: 9pt; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #d1d5db; color: #6b7280; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>{{ __('Datenschutz-Folgenabschätzung (Art. 35 DSGVO)') }}</h1>
    <div class="meta">
        {{ __('Verarbeitungstätigkeit') }}: {{ $activity->name }} ·
        {{ __('Ergebnis') }}: {{ $dpia->outcome->label() }}
        @if ($dpia->residual_risk) · {{ __('Restrisiko') }}: {{ $dpia->residual_risk }} @endif
        @if ($dpia->assessed_at) · {{ __('Bewertet am') }}: {{ $dpia->assessed_at->format('d.m.Y') }} @endif
    </div>

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

    <div class="footer">{{ config('app.name') }} · {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
