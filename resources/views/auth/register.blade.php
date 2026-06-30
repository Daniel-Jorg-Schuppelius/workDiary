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
        <title>{{ __('Registrieren') }} — {{ config('app.name', 'WorkDiary') }}</title>
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
                <a href="{{ route('home') }}" class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">WorkDiary</a>
                <div class="ml-auto flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="material-symbols-outlined text-base leading-none">dark_mode</span>
                    </button>
                    <x-button href="{{ route('login') }}" tone="ghost" size="sm">{{ __('Anmelden') }}</x-button>
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 pb-20 pt-24 lg:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <a href="{{ route('home') }}" class="inline-block">
                        <p class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">WorkDiary Next</p>
                        <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Organisation registrieren') }}</h1>
                    </a>
                    <p class="mt-3 text-sm text-base-content/70">{{ __('Legen Sie Ihre Organisation und Ihren Administrator-Account an.') }}</p>
                </div>

                <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
                    <form method="POST" action="{{ route('register') }}" class="space-y-5">
                        @csrf

                        {{-- Organisation --}}
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-base-content/50">{{ __('Organisation') }}</p>
                            <label for="org_name" class="mb-2 block text-sm font-medium text-base-content">{{ __('Name der Organisation') }}</label>
                            <input
                                id="org_name"
                                name="org_name"
                                type="text"
                                value="{{ old('org_name') }}"
                                autocomplete="organization"
                                autofocus
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('org_name') ring-2 ring-error/40 @enderror"
                                placeholder="{{ __('z. B. Klimatechnik Mustermann GmbH') }}"
                            >
                            @error('org_name')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="divider text-xs text-base-content/40">{{ __('Administrator-Account') }}</div>

                        {{-- Name --}}
                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-base-content">{{ __('Vollständiger Name') }}</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                autocomplete="name"
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('name') ring-2 ring-error/40 @enderror"
                                placeholder="{{ __('Max Mustermann') }}"
                            >
                            @error('name')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- E-Mail --}}
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-base-content">{{ __('E-Mail-Adresse') }}</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('email') ring-2 ring-error/40 @enderror"
                                placeholder="admin@example.com"
                            >
                            @error('email')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Passwort --}}
                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort') }}</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25 @error('password') ring-2 ring-error/40 @enderror"
                                placeholder="{{ __('Mindestens 8 Zeichen') }}"
                            >
                            @error('password')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Passwort bestätigen --}}
                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-base-content">{{ __('Passwort bestätigen') }}</label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25"
                                placeholder="{{ __('Passwort wiederholen') }}"
                            >
                        </div>

                        <x-button
                            type="submit"
                            tone="primary"
                            class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
                        >
                            ⇢ {{ __('Organisation anlegen') }}
                        </x-button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-base-content/70">
                    {{ __('Bereits registriert?') }}
                    <a href="{{ route('login') }}" class="text-primary transition hover:opacity-80">{{ __('Anmelden') }}</a>
                </p>
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
    </body>
</html>
