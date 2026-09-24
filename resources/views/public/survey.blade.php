{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : survey.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Öffentliche Umfrage-Teilnahme (Feature 090): token-basiert, ohne Login.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $survey->title }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-2xl p-4 space-y-4">
    <div class="rounded-box bg-base-100 p-6 shadow">
        <h1 class="text-xl font-semibold">{{ $survey->title }}</h1>
        @if ($survey->purpose)
            <p class="mt-1 text-sm text-base-content/70">{{ $survey->purpose }}</p>
        @endif
        @if ($survey->anonymous)
            <p class="mt-2 text-xs text-muted">{{ __('Ihre Antworten werden anonym gespeichert und sind nicht auf Sie rückführbar.') }}</p>
        @endif

        @if (session('error'))
            <div role="alert" class="alert alert-error mt-3 text-sm">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('surveys.public-store', $token) }}" class="mt-4 space-y-5">
            @csrf
            @foreach ($questions as $question)
                <div>
                    <x-field-input :field="$question->fieldDefinition()" prefix="" :wide="false" />
                </div>
            @endforeach
            <button type="submit" class="btn btn-primary w-full">{{ __('Antworten absenden') }}</button>
        </form>
    </div>
</main>
</body>
</html>
