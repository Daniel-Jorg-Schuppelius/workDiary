<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? __('Druckansicht') }} — WorkDiary</title>
    @php
        // $pageSize: 'A4 portrait' (default) | 'A4 landscape' | 'A3 landscape' | 'A3 portrait'
        $pageSize  = $pageSize  ?? 'A4 portrait';
        $pageMargin = $pageMargin ?? '8mm';
    @endphp
    <style>
        @page { size: {{ $pageSize }}; margin: {{ $pageMargin }}; }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 9pt;
            color: #111;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body { padding: 12px; }

        h1 { font-size: 14pt; margin: 0 0 4px; font-weight: 600; }
        h2 { font-size: 11pt; margin: 12px 0 4px; font-weight: 600; }
        .meta { color: #555; font-size: 8pt; margin-bottom: 10px; }
        .muted { color: #777; }
        .small { font-size: 7.5pt; }
        .right { text-align: right; }
        .center { text-align: center; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td {
            border: 1px solid #bdbdbd;
            padding: 3px 4px;
            vertical-align: top;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        th { background: #efefef; font-weight: 600; font-size: 8pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .weekend  { background: #f4f4f4; }
        .holiday  { background: #fff5d6; }
        .today    { background: #e8f1ff; }
        .sunday   { color: #c00; }
        .sunday.weekend { color: #c00; }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 7.5pt;
            font-weight: 600;
            color: #fff;
            line-height: 1.3;
            margin: 1px 1px 0 0;
            white-space: nowrap;
        }
        .badge-outline { background: transparent !important; color: #111 !important; border: 1px solid #888; }

        .legend { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; font-size: 8pt; }
        .legend .badge { font-size: 7pt; padding: 1px 5px; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .header .title { font-size: 14pt; font-weight: 700; }
        .header .subtitle { font-size: 9pt; color: #444; margin-top: 2px; }
        .header .meta { text-align: right; margin: 0; }

        .footer {
            margin-top: 12px;
            padding-top: 4px;
            border-top: 1px solid #bdbdbd;
            font-size: 7.5pt;
            color: #555;
            display: flex;
            justify-content: space-between;
        }

        /* timeline blocks (day briefing) */
        .timeline-row { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; page-break-inside: avoid; }
        .timeline-name { width: 30mm; font-weight: 600; font-size: 8pt; flex-shrink: 0; }
        .timeline-bar  { position: relative; flex: 1; height: 14px; background: #f0f0f0; border: 1px solid #d4d4d4; }
        .timeline-bar .block { position: absolute; top: 0; bottom: 0; color: #fff; font-size: 7pt; font-weight: 600; padding: 0 3px; line-height: 14px; overflow: hidden; }
        .timeline-axis { display: flex; margin-left: 30mm; padding-left: 6px; font-size: 7pt; color: #555; border-top: 1px solid #d4d4d4; padding-top: 2px; }
        .timeline-axis span { flex: 1; text-align: left; }

        /* Toolbar (only on screen) */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            padding: 8px 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .toolbar a, .toolbar button {
            display: inline-block;
            padding: 6px 14px;
            border: 1px solid #555;
            background: #fff;
            color: #111;
            text-decoration: none;
            cursor: pointer;
            font-size: 10pt;
            border-radius: 3px;
        }
        .toolbar .primary { background: #2563eb; color: #fff; border-color: #2563eb; }

        .pagebreak { page-break-after: always; break-after: page; }

        @media print {
            .no-print, .toolbar { display: none !important; }
            body { padding: 0; }
        }
    </style>
    @stack('print-styles')
</head>
<body>
    <div class="toolbar no-print">
        @isset($backUrl)
            <a href="{{ $backUrl }}">← {{ __('Zurück') }}</a>
        @endisset
        <button type="button" class="primary" onclick="window.print()">{{ __('Drucken / PDF speichern') }}</button>
    </div>

    @yield('content')
</body>
</html>
