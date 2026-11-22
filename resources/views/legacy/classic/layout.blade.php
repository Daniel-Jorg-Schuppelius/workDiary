{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : layout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Klassische Ansicht des Legacy-Bereichs: eigenes Dokument im Aussehen der Altportale, ohne App-Gerüst. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title') – {{ config('app.name', 'WorkDiary') }}</title>
        @include('partials.font-bootstrap', ['icons' => false])
        @vite(['resources/css/legacy-classic.css', 'resources/js/legacy-classic.js'])
    </head>
    <body class="kp-classic @yield('bodyClass')">
        <main class="kp-page">
            @yield('content')
        </main>
        <footer class="kp-footer">
            <a href="{{ route('legacy.view', ['mode' => \App\Legacy\Support\LegacyView::MODERN, 'back' => request()->getRequestUri()]) }}">{{ __('legacy_classic.switch_to_modern') }}</a>
        </footer>
    </body>
</html>
