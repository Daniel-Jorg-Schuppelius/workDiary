{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : case-file-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Interne Fallakte als PDF (MVP-349, fallakte.md §11): derselbe Datenteil wie
  diary/case-file.blade.php (diary/_case_file_sections, hier statisch) — inkl.
  INTERNER Einträge (Kommentare, interne Anhänge, Kommunikation) im Unterschied
  zum kundensichtbaren Portal-PDF (customer/diary/pdf.blade.php). Gerendert
  über die PDFWriterRegistry.
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
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 16px 0 5px; padding-bottom: 3px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; page-break-after: avoid; }
        .meta { color: #555; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        .kv th { width: 150px; }
        .sum { font-weight: bold; }
        .badge { display: inline-block; padding: 1px 5px; border: 1px solid #888; border-radius: 6px; font-size: 9px; white-space: nowrap; }
        .muted { color: #666; }
        .pre { white-space: pre-wrap; }
        .small { font-size: 9px; color: #666; }
    </style>
</head>
<body>
    @include('diary._case_file_sections', ['static' => true])
</body>
</html>
