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
        <title>{{ __('Lizenz erforderlich') }} — {{ config('app.name', 'WorkDiary') }}</title>
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
                <a href="{{ url('/') }}" class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">WorkDiary</a>
                <div class="ml-auto flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="text-base leading-none">◐</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 pb-20 pt-24 lg:px-10">
            <div class="w-full max-w-xl">
                <div class="mb-8 text-center">
                    <p class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">{{ __('Aktivierung') }}</p>
                    <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Lizenz erforderlich') }}</h1>
                    <p class="mt-3 text-sm text-base-content/70">
                        {{ __('Diese Instanz von :app benötigt eine gültige Lizenz, um genutzt zu werden.', ['app' => config('app.name', 'WorkDiary')]) }}
                    </p>
                </div>

                <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
                    <dl class="mb-6 grid grid-cols-1 gap-3 rounded-2xl border border-base-300 bg-base-200/60 px-4 py-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Domain') }}</dt>
                            <dd class="mt-1 font-mono text-sm text-base-content">{{ $host }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</dt>
                            <dd class="mt-1 text-sm text-base-content">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-medium text-warning">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-warning"></span>
                                    {{ __('Nicht aktiviert') }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    @if (! empty($message))
                        <div class="mb-5 rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning-content">
                            {{ $message }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                            <ul class="list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ url('/license') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="license_key" class="mb-2 block text-sm font-medium text-base-content">{{ __('Lizenzschlüssel') }}</label>
                            <textarea
                                id="license_key"
                                name="license_key"
                                rows="6"
                                required
                                spellcheck="false"
                                autocomplete="off"
                                class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 font-mono text-xs leading-relaxed text-base-content placeholder-base-content/40 transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25"
                                placeholder="eyJsaWNlbnNlX2lkIjoi..."
                            >{{ old('license_key') }}</textarea>
                            <p class="mt-2 text-xs text-base-content/60">
                                {{ __('Den vollständigen Schlüssel einschließlich Signaturteil einfügen.') }}
                            </p>
                        </div>

                        <x-button
                            type="submit"
                            tone="primary"
                            size="md"
                            class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold"
                        >
                            ⇢ {{ __('Lizenz aktivieren') }}
                        </x-button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-base-content/70">
                    {{ __('Noch keinen Schlüssel?') }}
                    <a href="mailto:info@daniel-schuppelius.de" class="text-primary transition hover:opacity-80">{{ __('Anbieter kontaktieren') }}</a>
                </p>
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
