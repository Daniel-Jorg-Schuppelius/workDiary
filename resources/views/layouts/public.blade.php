{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : public.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Layout der öffentlichen Seiten ohne Anmeldung (Startseite, Rechtstexte):
    x-guest-header/-footer nach App-Anatomie, Inhaltsbreite wie im App-Layout.
    Formularseiten (Anmeldung, Registrierung …) nutzen layouts/guest.

    Sections:
      title    Seitentitel (optional; „– <App>" hängt das Layout an)
      nav      Sprunglinks im Header (optional)
      content  Seiteninhalt (Pflicht)
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim" class="motion-safe:scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme-bootstrap')
        <title>@hasSection('title')@yield('title') – @endif{{ config('app.name', 'WorkDiary') }}</title>

        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/logo/workdiary-mark-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/logo/workdiary-mark-192.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo/workdiary-mark-192.png') }}">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                :root { color-scheme: dark; font-family: 'IBM Plex Sans', sans-serif; }
                * { box-sizing: border-box; }
                body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #082f49 0%, #0f172a 45%, #111827 100%); color: #e2e8f0; }
            </style>
        @endif
    </head>
    <body class="min-h-screen bg-base-200 text-base-content">
        <x-guest-header>
            @hasSection('nav')
                <x-slot:nav>@yield('nav')</x-slot:nav>
            @endif
        </x-guest-header>

        <main class="mx-auto w-full max-w-screen-2xl px-2 pb-20 pt-8 sm:px-4 xl:px-8 2xl:px-12">
            @yield('content')
        </main>

        <x-guest-footer />

        {{-- Theme-Toggle wird zentral von resources/js/layout.js (in app.js gebündelt)
             gesteuert. Ein zusätzliches Inline-Script hier würde einen ZWEITEN
             Click-Handler an denselben Button hängen → der Klick schaltet doppelt
             um und das Theme bleibt scheinbar stehen. Das Anti-Flash-Skript im
             <head> setzt nur das initiale Theme; den Umschalter macht layout.js. --}}
    </body>
</html>
