{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111827; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 18px; }
        .draft { padding: 8px 10px; margin-bottom: 16px; border: 1px solid #d97706; background: #fffbeb; font-weight: bold; }
        .content { white-space: pre-wrap; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #d1d5db; color: #6b7280; font-size: 8pt; }
    </style>
</head>
<body>
    <div class="draft">{{ __('ENTWURF – nicht automatisch versendet') }}</div>
    <h1>{{ $title }}</h1>
    <div class="content">{{ $body }}</div>
    <div class="footer">{{ $incident->incident_number }} · {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
