{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Read-Only-/Druck-Sicht eines ausgefüllten Formulars (Feature 032).
  Standalone-HTML mit Print-CSS (Muster: diary/case-file.blade.php).
  Anzeige läuft IMMER gegen fields_snapshot — nie gegen die Vorlage.
--}}
@php
    /** @var \App\Models\FormSubmission $submission */
    $values = (array) $submission->values;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('form.title.submission') }} #{{ $submission->id }} — WorkDiary</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; color: #111; margin: 24px auto; max-width: 960px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; white-space: nowrap; }
        tr:nth-child(even) td { background: #fafafa; }
        .kv th { width: 220px; background: #f5f5f5; }
        .muted { color: #666; }
        .pre { white-space: pre-wrap; }
        .actions { margin: 8px 0 16px; }
        .btn { padding: 6px 12px; border: 1px solid #555; background: #fff; cursor: pointer; text-decoration: none; color: #111; display: inline-block; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; max-width: none; }
            h2 { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button class="btn" onclick="window.print()">{{ __('form.action.print') }}</button>
        <a class="btn" href="{{ route('form-submissions.index') }}">{{ __('form.action.back') }}</a>
    </div>

    {{-- Kopf --}}
    <h1>{{ optional($submission->template)->name ?? __('form.title.submission') }}</h1>
    <div class="meta">
        {{ __('form.field.submitted_at') }}: {{ $submission->submitted_at->fdatetime() }}
        — {{ __('form.field.submitted_by') }}: {{ optional($submission->submitter)->name ?? '—' }}
        @if ($subjectLabel !== null)
            — {{ $subjectLabel }}
        @endif
    </div>

    {{-- Werte (gegen den Snapshot gerendert) --}}
    <h2>{{ __('form.title.values') }}</h2>
    <table class="kv">
        @foreach ((array) $submission->fields_snapshot as $field)
            <tr>
                <th>
                    {{ $field['label'] ?? $field['key'] ?? '—' }}
                    @if (filled($field['unit'] ?? null))
                        <span class="muted">({{ $field['unit'] }})</span>
                    @endif
                </th>
                <td class="pre">{{ \App\Services\Form\FormFieldDefinition::displayValue((array) $field, $values[$field['key'] ?? ''] ?? null) }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
