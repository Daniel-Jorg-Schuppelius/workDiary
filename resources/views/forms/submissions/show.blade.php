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
  Papierlayout wie das PDF (Feature 203, MVP-1651): forms.submissions._paper.
--}}
@php
    /** @var \App\Models\Form\FormSubmission $submission */
    $asOf = $submission->submitted_at ?? $submission->updated_at;
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
        .paper-head { margin-bottom: 12px; }
        .paper-head th { width: 18%; white-space: normal; }
        .paper-section-title { font-size: 13px; margin: 18px 0 6px; padding: 4px 8px; background: #eef1f5; border-left: 3px solid #333; text-transform: none; letter-spacing: 0; border-bottom: 0; }
        .paper-section td.paper-topic { width: 38%; font-weight: 600; }
        .wd-ballot__mark { margin-right: 12px; white-space: nowrap; }
        .wd-field-note-display { font-style: italic; color: #333; }
        .paper-foot { margin-top: 18px; padding-top: 4px; border-top: 1px solid #bbb; font-size: 10px; color: #555; }
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
        <x-button type="submit" tone="plain" size="md" data-print>{{ __('form.action.print') }}</x-button>
        <x-button :href="route('form-submissions.pdf', $submission)" tone="plain" size="md">{{ __('form.action.download_pdf') }}</x-button>
        <x-button :href="route('form-submissions.index')" tone="plain" size="md">{{ __('form.action.back') }}</x-button>
    </div>

    {{-- Kopf --}}
    <h1>{{ optional($submission->template)->name ?? __('form.title.submission') }}</h1>
    @if ($submission->isDraft())
        <p class="meta">{{ __('form.draft.show_hint') }}</p>
    @endif

    {{-- Werte (gegen den Snapshot gerendert) im Papierlayout --}}
    @include('forms.submissions._paper')

    <p class="paper-foot">
        {{ __('fields.paper.as_of') }}: {{ $asOf?->fdatetime() ?? '—' }}
        · {{ __('fields.paper.reference') }}: {{ $subjectLabel ?? ((optional($submission->template)->name ?? __('form.title.submission')) . ' #' . $submission->id) }}
    </p>
@include('partials.print-script')
</body>
</html>
