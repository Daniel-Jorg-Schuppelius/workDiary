{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : layout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Klassische Ansicht ohne App-Konto (Kundenportal, Callcenter): eigenes Dokument mit schlanker Kopfzeile wie die der App. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title') – {{ config('app.name', 'WorkDiary') }}</title>
        @include('partials.font-bootstrap')
        @vite(['resources/css/legacy-classic.css', 'resources/js/legacy-classic.js'])
    </head>
    <body class="kp-classic @yield('bodyClass')">
        <header class="kp-topbar">
            <span class="kp-topbar__title"><span class="kp-topbar__brand">{{ config('app.name', 'WorkDiary') }}</span> / @yield('title')</span>
            @include('legacy._view_switch', ['variant' => 'classic'])
        </header>
        <main class="kp-page">
            @yield('content')
        </main>
    </body>
</html>
