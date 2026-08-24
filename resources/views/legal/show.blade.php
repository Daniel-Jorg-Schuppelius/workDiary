{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Anti-Flash-Theme (ein Partial statt 17 Kopien; Vollaudit 2026-07, M51). --}}
        @include('partials.theme-bootstrap')
        <title>{{ $title }} – {{ config('app.name', 'WorkDiary') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                :root {
                    color-scheme: dark;
                    font-family: 'IBM Plex Sans', sans-serif;
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;
                    background: linear-gradient(135deg, #082f49 0%, #0f172a 45%, #111827 100%);
                    color: #e2e8f0;
                }
            </style>
        @endif
    </head>
    <body class="min-h-screen bg-base-200 text-base-content">
        <header class="border-b border-base-300 bg-base-100">
            <div class="mx-auto flex w-full max-w-4xl items-center justify-between gap-4 px-6 py-4 lg:px-10">
                <a href="{{ route('home') }}" class="font-['Space_Grotesk'] text-lg font-bold tracking-tight text-base-content transition hover:text-primary">
                    {{ config('app.name', 'WorkDiary') }}
                </a>
                <a href="{{ route('home') }}" class="text-sm text-base-content/70 transition hover:text-base-content">
                    {{ __('Zurück zur Startseite') }}
                </a>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-6 py-10 lg:px-10">
            <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ $title }}</h1>

            <section class="mt-8 rounded-box border border-base-300 bg-base-100 p-6 shadow-xs sm:p-8">
                @if ($content !== null)
                    {{-- Betreiber-Klartext: escaped + Zeilenumbrüche erhalten. --}}
                    <div class="whitespace-pre-line text-sm leading-relaxed text-base-content/90">{{ $content }}</div>
                @else
                    <p class="text-sm text-base-content/70">
                        {{ __('Der Betreiber dieser Installation hat diesen Rechtstext noch nicht hinterlegt.') }}
                    </p>
                    <p class="mt-3 text-sm text-base-content/50">
                        {{ __('Hinterlegt wird der Inhalt in den Systemeinstellungen (Administration → Einstellungen, Schlüssel :key).', ['key' => $settingKey]) }}
                    </p>
                @endif
            </section>
        </main>

        <footer class="border-t border-base-300 bg-base-100">
            <div class="mx-auto flex w-full max-w-4xl flex-col items-center justify-between gap-4 px-6 py-6 text-sm text-base-content/70 sm:flex-row lg:px-10">
                <span><x-footer-copyright /></span>
                <nav class="flex items-center gap-5">
                    <a href="{{ route('legal.imprint') }}" class="transition hover:text-base-content">{{ __('Impressum') }}</a>
                    <a href="{{ route('legal.privacy') }}" class="transition hover:text-base-content">{{ __('Datenschutz') }}</a>
                    <a href="{{ route('legal.accessibility') }}" class="transition hover:text-base-content">{{ __('Barrierefreiheit') }}</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
