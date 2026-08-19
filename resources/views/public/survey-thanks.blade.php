{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : survey-thanks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('Vielen Dank!') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-2xl p-4">
    <div class="rounded-box bg-base-100 p-8 text-center shadow">
        <span class="material-symbols-outlined text-5xl text-success" aria-hidden="true">check_circle</span>
        <h1 class="mt-2 text-xl font-semibold">{{ __('Vielen Dank!') }}</h1>
        <p class="mt-1 text-sm text-base-content/70">{{ __('Ihre Antworten zu „:title" sind angekommen.', ['title' => $survey->title]) }}</p>
    </div>
</main>
</body>
</html>
