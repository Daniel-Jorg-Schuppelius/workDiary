{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Öffentliche, login-freie Angebots-Annahme (Feature 066, MVP-170):
  token-basiert (nur Hash gespeichert), datensparsam — Positionen, Summen
  und Bindefrist; Annahme/Teilannahme/Ablehnung mit Zeitstempel.
  Variablen: $quote (Quote), $token (string), $decided (bool)
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('Angebot :nr', ['nr' => $quote->number]) }}</title>
@include('partials.font-bootstrap', ['icons' => false])
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4 space-y-4">
    <div class="rounded-box bg-base-100 p-4 shadow">
        <div class="mb-1 flex items-center gap-2 text-xs text-muted">
            <x-status-badge tone="plain" outline>{{ __('Angebot') }}</x-status-badge>
            <span>{{ $quote->number }} · V{{ $quote->version }}</span>
        </div>
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Angebot :nr', ['nr' => $quote->number]) }}</h1>
        @if ($quote->valid_until)
            <div class="mt-1 text-sm text-base-content/70">{{ __('Gültig bis :date', ['date' => $quote->valid_until->fdate()]) }}</div>
        @endif
    </div>

    @if (session('status'))
        <div role="status" class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-error">{{ session('error') }}</div>
    @endif

    @include('quotes._customer_decision', [
        'quote' => $quote,
        'action' => $decided ? null : route('quotes.portal.decide', $quote),
        'hidden' => ['token' => $token],
        'withReason' => false,
    ])
</main>
</body>
</html>
