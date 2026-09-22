{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : agreement-thanks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('contract-signing.public.thanks_title') }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-xl p-4">
    <div role="status" class="alert alert-success">
        <span>
            @if ($outcome === 'uploaded')
                {{ __('contract-signing.public.thanks_uploaded') }}
            @else
                {{ __('contract-signing.public.thanks_signed') }}
            @endif
        </span>
    </div>
    <p class="mt-3 text-sm text-muted">{{ __('contract-signing.public.thanks_hint') }}</p>
</main>
</body>
</html>
