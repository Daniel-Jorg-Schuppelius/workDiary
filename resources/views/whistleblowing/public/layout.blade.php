<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Meldestelle')</title>
    <link rel="stylesheet" href="{{ asset('whistleblowing.css') }}">
</head>
<body>
    <main class="wb-wrap">
        <header class="wb-header">
            <h1>{{ ($portal ?? null)?->organization?->name ?? __('Meldestelle') }}</h1>
            <p class="wb-sub">{{ __('Vertraulicher Meldekanal') }}</p>
        </header>

        @yield('content')

        <footer class="wb-footer">
            <p>{{ __('Dieses Portal speichert keine IP-Adresse zur Meldung. Echte Anonymität hängt auch von Ihrem Netzwerk und Gerät ab. Nutzen Sie es bei Bedarf aus einem privaten Netz.') }}</p>
        </footer>
    </main>
</body>
</html>
