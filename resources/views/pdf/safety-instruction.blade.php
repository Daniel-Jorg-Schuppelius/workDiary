{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : safety-instruction.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var \App\Models\Safety\SafetyInstruction $instruction */
    /** @var array<int, string|null> $signatures */
    /** @var \Illuminate\Support\Carbon $generatedAt */
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('safety.register.pdf.instruction_title') }} {{ $instruction->displayNo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 3mm 0; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .meta th { text-align: left; width: 45mm; padding: 1mm 2mm; background: #f3f3f3; }
        .meta td { padding: 1mm 2mm; }
        .statement { margin: 3mm 0; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th, table.rows td { border: 1px solid #ccc; padding: 1.5mm; vertical-align: top; font-size: 9pt; }
        table.rows th { background: #eee; text-align: left; }
        table.rows img { max-height: 14mm; max-width: 45mm; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 7.5pt; color: #444; }
        .muted { color: #555; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>{{ __('safety.register.pdf.instruction_title') }} {{ $instruction->displayNo() }}</h1>
    <table class="meta">
        <tr><th>{{ __('safety.register.field.topic') }}</th><td>{{ $instruction->topic }}</td></tr>
        <tr><th>{{ __('safety.register.field.held_on') }}</th><td>{{ $instruction->held_on->fdate() }}</td></tr>
        <tr><th>{{ __('safety.register.field.instructor') }}</th><td>{{ $instruction->instructor?->name ?? '–' }}</td></tr>
        @if ($instruction->assessment)
            <tr><th>{{ __('safety.register.field.assessment') }}</th><td>{{ $instruction->assessment->displayNo() }} · {{ $instruction->assessment->area }}</td></tr>
        @endif
        <tr><th>{{ __('safety.register.field.repeat_interval_months') }}</th><td>{{ $instruction->repeat_interval_months ?? '–' }}</td></tr>
        @if ($instruction->notes)
            <tr><th>{{ __('safety.register.field.notes') }}</th><td>{{ $instruction->notes }}</td></tr>
        @endif
    </table>

    <p class="statement">{{ __('safety.register.pdf.statement') }}</p>

    <table class="rows">
        <thead>
            <tr>
                <th>{{ __('safety.register.field.user') }}</th>
                <th>{{ __('safety.register.field.signed_at') }}</th>
                <th>{{ __('safety.register.field.method') }}</th>
                <th>{{ __('safety.register.pdf.signature') }}</th>
                <th>{{ __('safety.register.field.next_due_on') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($instruction->participants as $participant)
                <tr>
                    <td>{{ $participant->signer_name ?? $participant->user?->name ?? '–' }}</td>
                    <td>{{ $participant->signed_at?->orgTz()->format('d.m.Y H:i') ?? __('safety.register.pdf.open') }}</td>
                    <td>{{ $participant->method?->label() ?? '–' }}</td>
                    <td>
                        @if (($signatures[$participant->id] ?? null) !== null)
                            <img src="{{ $signatures[$participant->id] }}" alt="{{ __('safety.register.pdf.signature') }}"><br>
                        @endif
                        @if ($participant->hash)
                            <span class="mono">{{ __('safety.register.pdf.hash') }}: {{ substr($participant->hash, 0, 16) }}</span>
                        @endif
                    </td>
                    <td>{{ $participant->next_due_on?->fdate() ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted">{{ __('safety.register.pdf.generated', ['date' => $generatedAt->orgTz()->format('d.m.Y H:i')]) }}</p>
</body>
</html>
