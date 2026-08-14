{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : guest.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Gemeinsames Guest-Layout (Vollaudit 2026-07, M51) — ersetzt das zuvor
    7-fach kopierte Standalone-Skelett (auth/* + account/password): Head mit
    Anti-Flash-Theme-Partial, Favicons, Vite+Fallback, fixed Brand-Header mit
    Theme-Toggle, zentrierte Karte mit Brand-Kopf, fixed Footer.

    Sections:
      title          Seitentitel (Pflicht; „— <Brand>" hängt das Layout an)
      headline       H1 im Karten-Kopf (Pflicht)
      intro          optionaler Untertitel (Text oder eigenes Markup)
      content        Karteninhalt + alles darunter (Pflicht)
      header-action  Button rechts im Header (Default: Anmelden;
                     leer definieren = kein Button)
      wrapper-attrs  Zusatz-Attribute am max-w-md-Wrapper (z. B. x-data)
      after-body     Partials/Skripte vor </body> (z. B. WebAuthn)
--}}
@php
    $brandName = isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary');
    $brandLogo = isset($branding) && $branding ? $branding->logoUrl() : asset('img/logo/workdiary-logo-512.png');
    $brandSlogan = isset($branding) && $branding ? $branding->slogan() : null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.theme-bootstrap')
        <title>@yield('title') — {{ $brandName }}</title>

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
    <body class="min-h-screen bg-primary-content text-base-content">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-between gap-4 px-4 py-3 xl:px-8 2xl:px-12">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-10 w-auto max-w-48 object-contain">
                    @else
                        <span class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ $brandName }}</span>
                    @endif
                </a>
                <div class="ml-auto flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="material-symbols-outlined text-base leading-none">dark_mode</span>
                    </button>
                    @hasSection('header-action')
                        @yield('header-action')
                    @else
                        <x-button href="{{ route('login') }}" tone="ghost" size="sm" class="gap-1" icon="login">{{ __('Anmelden') }}</x-button>
                    @endif
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 pb-20 pt-24 lg:px-10">
            <div class="w-full max-w-md" @yield('wrapper-attrs')>
                <div class="mb-8 text-center">
                    <a href="{{ route('home') }}" class="inline-block">
                        @if ($brandLogo)
                            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="mx-auto mb-4 h-20 w-auto max-w-xs object-contain">
                        @else
                            {{-- Kein Logo gesetzt: App-Name als Text-Fallback rendern. --}}
                            <p class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ $brandName }}</p>
                        @endif
                        @if ($brandSlogan)
                            <p class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ $brandSlogan }}</p>
                        @endif
                        <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">@yield('headline')</h1>
                    </a>
                    @hasSection('intro')
                        <div class="mt-3 text-sm text-base-content/70">@yield('intro')</div>
                    @endif
                </div>

                @yield('content')
            </div>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                <x-footer-copyright />
            </div>
        </footer>
        {{-- Theme-Toggle wird zentral von resources/js/layout.js (in app.js gebündelt)
             gesteuert. Ein zusätzliches Inline-Script hier würde einen ZWEITEN
             Click-Handler an denselben Button hängen → der Klick schaltet doppelt
             um und das Theme bleibt scheinbar stehen. Das Anti-Flash-Skript im
             <head> setzt nur das initiale Theme; den Umschalter macht layout.js. --}}
        @yield('after-body')
    </body>
</html>
