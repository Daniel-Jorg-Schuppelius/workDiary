{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : layout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
            @php $wbLocales = ($portal ?? null)?->offeredLocales() ?? []; @endphp
            @if (count($wbLocales) > 1)
                <nav class="wb-sub" aria-label="{{ __('Sprache') }}">
                    @foreach ($wbLocales as $code)
                        @if ($code === app()->getLocale())
                            <strong lang="{{ $code }}">{{ \App\Support\Locales::native($code) }}</strong>
                        @else
                            <a href="{{ route('whistleblowing.portal', ['portal' => $portal->public_slug, 'lang' => $code]) }}" lang="{{ $code }}" hreflang="{{ $code }}">{{ \App\Support\Locales::native($code) }}</a>
                        @endif
                        @if (! $loop->last) · @endif
                    @endforeach
                </nav>
            @endif
        </header>

        @yield('content')

        <footer class="wb-footer">
            <p>{{ __('Dieses Portal speichert keine IP-Adresse zur Meldung. Echte Anonymität hängt auch von Ihrem Netzwerk und Gerät ab. Nutzen Sie es bei Bedarf aus einem privaten Netz.') }}</p>
        </footer>
    </main>
</body>
</html>
