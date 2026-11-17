{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  PDF-Ausgabe eines ausgefüllten Formulars (Feature 032, Rang 31) — dompdf.
  Immer gegen fields_snapshot gerendert (Versionssicherheit).
  Papierlayout (Feature 203, MVP-1651): Kopftabelle, je Abschnitt „Thema /
  Angaben“, Auswahl als ☐/☑, Fußzeile Stand · Bezug · Seite x von y. Die
  Seitenzahl setzt dompdf (counter(page)); die Gesamtzahl kommt aus einem
  ersten Lauf des FormSubmissionPdfRenderer ($pages, null im ersten Lauf).
--}}
@php
    /** @var \App\Models\Form\FormSubmission $submission */
    $pages ??= null;
    $asOf = $submission->submitted_at ?? $submission->updated_at;
    $reference = $subjectLabel ?? ((optional($submission->template)->name ?? __('form.title.submission')) . ' #' . $submission->id);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('form.title.submission') }} #{{ $submission->id }}</title>
    <style>
        @page { margin: 16mm 14mm 20mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; }
        h1 { font-size: 17px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        .paper-head { margin-bottom: 10px; }
        .paper-head th, .paper-head td, .paper-section th, .paper-section td { border: 1px solid #8f99a3; padding: 4px 6px; vertical-align: top; text-align: left; }
        .paper-head th { width: 17%; background: #f1f3f5; font-weight: bold; }
        .paper-section-title { font-size: 11.5px; margin: 14px 0 4px; padding: 3px 6px; background: #e9edf1; border-left: 3px solid #333; page-break-after: avoid; }
        .paper-section thead th { background: #f1f3f5; font-size: 9.5px; }
        .paper-section td.paper-topic { width: 38%; font-weight: bold; }
        .paper-section tr { page-break-inside: avoid; }
        .muted { color: #555; }
        .pre { white-space: pre-wrap; }
        .wd-ballot__mark { margin-right: 10px; white-space: nowrap; }
        .wd-field-note-display { color: #333; font-style: italic; }
        .paper-foot { position: fixed; bottom: -12mm; left: 0; right: 0; padding-top: 3px; border-top: 1px solid #8f99a3; font-size: 8.5px; color: #444; }
        .paper-page:after { content: counter(page); }
        /* Die Fußzeile trägt die Seitenzahl; eine zweite aus dem Dokumentdesign entfällt. */
        .dd-pagenum { display: none; }
    </style>
</head>
<body>
    <div class="paper-foot">
        {{ __('fields.paper.as_of') }}: {{ $asOf?->orgTz()->format('d.m.Y H:i') ?? '—' }}
        · {{ __('fields.paper.reference') }}: {{ $reference }}
        · <span class="paper-page">{{ __('fields.paper.page') }} </span>@if ($pages !== null) {{ __('fields.paper.of_pages', ['pages' => $pages]) }}@endif
    </div>

    <h1>{{ optional($submission->template)->name ?? __('form.title.submission') }}</h1>

    @include('forms.submissions._paper')
</body>
</html>
