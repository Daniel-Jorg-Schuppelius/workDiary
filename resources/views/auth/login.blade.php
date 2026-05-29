<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            (function () {
                var savedTheme = localStorage.getItem('workDiaryTheme');
                var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = savedTheme || (prefersLight ? 'corporate' : 'dim');
                var root = document.documentElement;
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
            })();
        </script>
        <title>{{ __('Anmelden') }} — {{ isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary') }}</title>

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
                @php
                    $_loginBrandLogo = isset($branding) && $branding ? $branding->logoUrl() : null;
                    $_loginBrandName = isset($branding) && $branding ? $branding->appName() : 'WorkDiary';
                @endphp
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    @if ($_loginBrandLogo)
                        <img src="{{ $_loginBrandLogo }}" alt="{{ $_loginBrandName }}" class="h-10 w-auto max-w-48 object-contain">
                    @else
                        <span class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ $_loginBrandName }}</span>
                    @endif
                </a>
                <div class="ml-auto flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="text-base leading-none">◐</span>
                    </button>
                    <a href="{{ route('home') }}" class="btn btn-sm btn-ghost gap-1">
                        <x-icon name="home" />
                        <span>{{ __('Startseite') }}</span>
                    </a>
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 pb-20 pt-24 lg:px-10">
            <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                @php
                    $_loginSlogan = isset($branding) && $branding ? $branding->slogan() : null;
                @endphp
                <a href="{{ route('home') }}" class="inline-block">
                    @if ($_loginBrandLogo)
                        <img src="{{ $_loginBrandLogo }}" alt="{{ $_loginBrandName }}"
                             class="mx-auto mb-4 h-20 w-auto max-w-xs object-contain">
                    @else
                        {{-- Kein Logo gesetzt: App-Name als Text-Fallback rendern. --}}
                        <p class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ $_loginBrandName }}</p>
                    @endif
                    @if ($_loginSlogan)
                        <p class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ $_loginSlogan }}</p>
                    @endif
                    <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Anmelden') }}</h1>
                </a>
                <p class="mt-3 text-sm text-base-content/70">{{ __('Benutzerdaten aus dem bestehenden Auftragsbuch-System.') }}</p>
            </div>

            <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="mb-2 block text-sm font-medium text-base-content">{{ __('Benutzername') }}</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            value="{{ old('username') }}"
                            autocomplete="username"
                            autofocus
                            class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('username') ring-2 ring-error/40 @enderror"
                            placeholder="{{ __('Benutzername') }}"
                        >
                        @error('username')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort') }}</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25"
                            placeholder="{{ __('Passwort') }}"
                        >
                    </div>

                    <div class="flex items-center gap-3">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                        >
                        <label for="remember" class="text-sm text-base-content/80">{{ __('Angemeldet bleiben') }}</label>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
                    >
                        ⇢ {{ __('Anmelden') }}
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-base-content/70">
                <a href="{{ route('home') }}" class="text-primary transition hover:opacity-80">← {{ __('Zurück zur Startseite') }}</a>
            </p>
            @if (config('app.registration_enabled'))
            <p class="mt-3 text-center text-sm text-base-content/70">
                {{ __('Noch kein Account?') }}
                <a href="{{ route('register') }}" class="text-primary transition hover:opacity-80">{{ __('Organisation registrieren') }}</a>
            </p>
            @endif
            </div>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                &copy; {{ date('Y') }} {{ isset($branding) && $branding ? $branding->appName() : 'WorkDiary' }}. {{ __('Alle Rechte vorbehalten.') }}
            </div>
        </footer>
        <script>
            (function () {
                var root = document.documentElement;
                var toggle = document.querySelector('[data-theme-toggle]');
                var label = document.querySelector('[data-theme-label]');

                function setTheme(theme) {
                    root.setAttribute('data-theme', theme);
                    root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
                    localStorage.setItem('workDiaryTheme', theme);
                    if (label) {
                        label.textContent = theme === 'corporate' ? '☾' : '◐';
                    }
                }

                var activeTheme = root.getAttribute('data-theme') === 'corporate' ? 'corporate' : 'dim';
                setTheme(activeTheme);

                if (toggle) {
                    toggle.addEventListener('click', function () {
                        var nextTheme = root.getAttribute('data-theme') === 'corporate' ? 'dim' : 'corporate';
                        setTheme(nextTheme);
                    });
                }
            })();
        </script>
    </body>
</html>
