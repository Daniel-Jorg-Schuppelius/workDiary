<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>@yield('pdf-title')</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin: 0 0 12px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .footer { margin-top: 14px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <h1>@yield('pdf-heading')</h1>

    <p class="meta">
        @yield('pdf-meta')
    </p>

    @yield('pdf-table')

    <p class="footer">Generiert von WorkDiary am {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
