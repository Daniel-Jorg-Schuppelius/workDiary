{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zahlungslink ohne Weiterleitung (MVP-1067): bezahlt, in Bearbeitung, nicht zahlbar oder Anbieter gestört. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('payments.page.title', ['number' => $invoice->number]) }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 32rem; margin: 10vh auto; padding: 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        p { line-height: 1.5; }
        .muted { color: #64748b; font-size: .875rem; }
    </style>
</head>
<body>
    <main>
        <p class="muted">{{ $invoice->organization->name }}</p>
        <h1>{{ __('payments.page.title', ['number' => $invoice->number]) }}</h1>
        <p>{{ __('payments.page.' . $reason) }}</p>
    </main>
</body>
</html>
