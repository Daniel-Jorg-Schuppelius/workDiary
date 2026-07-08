{{--
  PDF-Ausgabe eines ausgefüllten Formulars (Feature 032, Rang 31) — dompdf.
  Immer gegen fields_snapshot gerendert (Versionssicherheit). Abgeleitet von
  submissions/show.blade.php, ohne Bedien-Elemente.
--}}
@php
    /** @var \App\Models\FormSubmission $submission */
    $values = (array) $submission->values;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('form.title.submission') }} #{{ $submission->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; }
        .kv th { width: 220px; }
        .muted { color: #666; }
        .pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{{ optional($submission->template)->name ?? __('form.title.submission') }}</h1>
    <div class="meta">
        {{ __('form.field.submitted_at') }}: {{ optional($submission->submitted_at)->format('d.m.Y H:i') }}
        — {{ __('form.field.submitted_by') }}: {{ optional($submission->submitter)->name ?? '—' }}
        @if ($subjectLabel !== null)
            — {{ $subjectLabel }}
        @endif
    </div>

    <h2>{{ __('form.title.values') }}</h2>
    <table class="kv">
        @foreach ((array) $submission->fields_snapshot as $field)
            {{-- Bedingungslogik (Rang 33): ausgeblendete Felder gar nicht drucken. --}}
            @continue(! \App\Services\Form\FormFieldDefinition::isVisible((array) $field, $values))
            <tr>
                <th>
                    {{ $field['label'] ?? $field['key'] ?? '—' }}
                    @if (filled($field['unit'] ?? null))
                        <span class="muted">({{ $field['unit'] }})</span>
                    @endif
                </th>
                <td class="pre">
                    {{ \App\Services\Form\FormFieldDefinition::displayValue((array) $field, $values[$field['key'] ?? ''] ?? null) }}
                    @php $att = $submission->attachmentByMeta('field:' . ($field['key'] ?? '')); @endphp
                    @if ($att && \Illuminate\Support\Str::startsWith((string) $att->mime, 'image/'))
                        <br><img src="data:{{ $att->mime }};base64,{{ base64_encode((string) \Illuminate\Support\Facades\Storage::disk($att->disk)->get($att->path)) }}" style="max-height:120px; max-width:100%; margin-top:4px;">
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</body>
</html>
