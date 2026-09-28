{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : root.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einstieg der Doku-Website (MVP-971): Sprachauswahl. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('help.site._head', ['pageTitle' => config('app.name', 'WorkDiary'), 'base' => ''])
</head>
<body>
    <main class="site-main">
        <h1>{{ __(':app-Dokumentation', ['app' => config('app.name', 'WorkDiary')]) }}</h1>
        <ul class="site-languages">
            @foreach ($languages as $language)
                <li><a href="{{ $language['code'] }}/index.html" lang="{{ $language['code'] }}">{{ $language['name'] }}</a></li>
            @endforeach
        </ul>
    </main>
</body>
</html>
