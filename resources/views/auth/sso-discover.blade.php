<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script @cspNonce>
            (function () {
                var savedTheme = localStorage.getItem('workDiaryTheme');
                var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = savedTheme || (prefersLight ? 'corporate' : 'dim');
                var root = document.documentElement;
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
            })();
        </script>
        <title>{{ __('Mit Single-Sign-on anmelden') }} — {{ isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary') }}</title>

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
        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 pb-20 pt-24 lg:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Mit Single-Sign-on anmelden') }}</h1>
                    <p class="mt-3 text-sm text-base-content/70">{{ __('sso.discover.hint') }}</p>
                </div>

                <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
                    {{-- Bewusst GET: die Kennung ist kein Geheimnis, der Flow bleibt lesezeichenfähig. --}}
                    <form method="GET" action="{{ route('sso.discover') }}" class="space-y-5">
                        <div>
                            <label for="org" class="mb-2 block text-sm font-medium text-base-content">{{ __('sso.discover.org_label') }}</label>
                            <input
                                id="org"
                                name="org"
                                type="text"
                                value="{{ old('org', request('org')) }}"
                                autofocus
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('org') ring-2 ring-error/40 @enderror"
                                placeholder="{{ __('sso.discover.org_placeholder') }}"
                            >
                            @error('org')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-button
                            type="submit"
                            tone="primary"
                            class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
                        >
                            ⇢ {{ __('sso.discover.submit') }}
                        </x-button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-base-content/70">
                    <a href="{{ route('login') }}" class="text-primary transition hover:opacity-80">← {{ __('sso.discover.back_to_login') }}</a>
                </p>
            </div>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                <x-footer-copyright />
            </div>
        </footer>
    </body>
</html>
