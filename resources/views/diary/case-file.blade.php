{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : case-file.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Fallakte (MVP-013): zusammenhängende Read-Only-/Druck-Sicht eines Auftrags.
  Standalone-HTML mit Print-CSS (Muster: diary/export-pdf.blade.php); der
  Datenteil liegt mit der PDF-Fassung gemeinsam in diary/_case_file_sections.
--}}
@php
    /** @var \App\Models\Diary\DiaryEntry $diary */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('timeline.title.case_file') }} #{{ $diary->id }} — WorkDiary</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; color: #111; margin: 24px auto; max-width: 960px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; white-space: nowrap; }
        tr:nth-child(even) td { background: #fafafa; }
        .kv th { width: 180px; background: #f5f5f5; }
        .sum { font-weight: 600; }
        .badge { display: inline-block; padding: 1px 6px; border: 1px solid #888; border-radius: 8px; font-size: 10px; white-space: nowrap; }
        .muted { color: #666; }
        .small { font-size: 10px; color: #666; }
        .inline { display: inline; }
        .btn-xs { padding: 2px 6px; font-size: 10px; }
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
        <x-button type="submit" tone="plain" size="md" data-print>{{ __('timeline.action.print') }}</x-button>
        <x-button :href="route('diary.case-file.pdf', $diary)" tone="plain" size="md">{{ __('timeline.action.pdf') }}</x-button>
        <x-button :href="route('diary.show', $diary)" tone="plain" size="md">{{ __('timeline.action.back_to_order') }}</x-button>
    </div>

    @include('diary._case_file_sections', ['static' => false])
@include('partials.print-script')
</body>
</html>
