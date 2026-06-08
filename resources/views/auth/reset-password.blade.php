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
        @php
            $brandName = isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary');
            $brandLogo = isset($branding) && $branding ? $branding->logoUrl() : asset('img/logo/workdiary-logo-512.png');
        @endphp
        <title>{{ __('Passwort zurücksetzen') }} — {{ $brandName }}</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
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
                        <span data-theme-label class="text-base leading-none">◐</span>
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-sm btn-ghost gap-1">
                        <x-icon name="login" />
                        <span>{{ __('Anmelden') }}</span>
                    </a>
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 pb-20 pt-24 lg:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="mx-auto mb-4 h-20 w-auto max-w-xs object-contain">
                    @endif
                    <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Passwort zurücksetzen') }}</h1>
                    <p class="mt-3 text-sm text-base-content/70">{{ __('Wählen Sie ein neues, sicheres Passwort.') }}</p>
                </div>

                <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
                    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-base-content">{{ __('E-Mail') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required
                                   class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('email') ring-2 ring-error/40 @enderror">
                            @error('email')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Neues Passwort') }}</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                                   class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('password') ring-2 ring-error/40 @enderror">
                            @error('password')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-base-content">{{ __('Bestätigen') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                                   class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
                        </div>

                        <button type="submit" class="btn btn-primary w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                            ⇢ {{ __('Passwort speichern') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                <x-footer-copyright />
            </div>
        </footer>
        <script @cspNonce>
            (function () {
                var root = document.documentElement;
                var toggle = document.querySelector('[data-theme-toggle]');
                var label = document.querySelector('[data-theme-label]');
                function setTheme(theme) {
                    root.setAttribute('data-theme', theme);
                    root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
                    localStorage.setItem('workDiaryTheme', theme);
                    if (label) { label.textContent = theme === 'corporate' ? '☾' : '◐'; }
                }
                setTheme(root.getAttribute('data-theme') === 'corporate' ? 'corporate' : 'dim');
                if (toggle) {
                    toggle.addEventListener('click', function () {
                        setTheme(root.getAttribute('data-theme') === 'corporate' ? 'dim' : 'corporate');
                    });
                }
            })();
        </script>
    </body>
</html>
