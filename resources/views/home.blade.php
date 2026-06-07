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
        <title>{{ config('app.name', 'WorkDiary') }}</title>
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
        @php
            // Funktionsgruppen für die Übersicht – Icons spiegeln die Hauptnavigation.
            $features = [
                ['icon' => 'list_alt',       'title' => __('Arbeitstagebuch & Aufgaben'), 'text' => __('Vorgänge erfassen und in Arbeitsliste, Kanban und Wochenansicht behalten – inklusive Dienstplänen.')],
                ['icon' => 'schedule',       'title' => __('Zeit & Schicht'),             'text' => __('Stempeluhr, Schichtpläne, Stundenzettel und Arbeitszeitkonto an einem Ort.')],
                ['icon' => 'directions_car', 'title' => __('Touren & Fahrzeuge'),         'text' => __('Touren planen, Fahrtenbuch führen, Fahrzeuge sowie Tank- und Ladelogs verwalten.')],
                ['icon' => 'receipt_long',   'title' => __('Spesen & Abrechnung'),        'text' => __('Spesen und Verpflegungspauschalen erfassen, Rechnungen und Belege erstellen.')],
                ['icon' => 'folder_special', 'title' => __('Kunden & Projekte'),          'text' => __('Kunden, Lieferanten, Projekte sowie Produkte und Leistungen organisieren.')],
                ['icon' => 'groups',         'title' => __('Team & Personal'),            'text' => __('Mitarbeiter, Teams, Urlaub, Krankmeldungen, Qualifikationen und Lohn im Blick.')],
            ];

            $steps = [
                ['icon' => 'edit_note',       'title' => __('Erfassen'),  'text' => __('Arbeitszeiten, Vorgänge, Touren und Spesen direkt im Einsatz dokumentieren.')],
                ['icon' => 'event_available', 'title' => __('Planen'),    'text' => __('Schichten, Dienstpläne und Projekte koordinieren – das ganze Team synchron.')],
                ['icon' => 'request_quote',   'title' => __('Abrechnen'), 'text' => __('Stundenzettel, Belege und Rechnungen sauber auswerten und abrechnen.')],
            ];
        @endphp

        <header class="fixed inset-x-0 top-0 z-50 border-b border-base-300 bg-base-100/95 shadow-xs backdrop-blur">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-between gap-4 px-6 py-3 xl:px-8 2xl:px-12">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <img src="{{ asset('img/logo/workdiary-logo-512.png') }}" alt="WorkDiary"
                         class="h-10 w-auto max-w-48 object-contain">
                </a>
                <div class="ml-auto flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="text-base leading-none">◐</span>
                    </button>
                    <x-icon-btn icon="login" tone="primary" size="sm" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 pb-24 pt-24 lg:px-10">
            {{-- Hero --}}
            <section class="relative overflow-hidden rounded-box border border-base-300 bg-base-100 px-6 py-16 text-center shadow-xs sm:px-12 sm:py-20">
                <div class="pointer-events-none absolute inset-0 -z-10 opacity-70"
                     style="background:
                        radial-gradient(40rem 22rem at 50% -10%, color-mix(in oklab, var(--color-primary) 22%, transparent), transparent 70%),
                        radial-gradient(32rem 20rem at 85% 120%, color-mix(in oklab, var(--color-secondary) 16%, transparent), transparent 70%);">
                </div>

                <img src="{{ asset('img/logo/workdiary-logo-768.png') }}" alt="WorkDiary"
                     class="mx-auto h-20 w-auto max-w-xs object-contain">

                <h1 class="mx-auto mt-8 max-w-3xl font-['Space_Grotesk'] text-4xl font-bold tracking-tight text-base-content md:text-5xl">
                    {{ __('Das Auftragsbuch fürs ganze Tagesgeschäft') }}
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-base-content/75">
                    {{ __('Zeit, Touren, Spesen und Abrechnung – ein Werkzeug für Einsatz, Planung und Team.') }}
                </p>

                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <x-icon-btn icon="login" tone="primary" size="md" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                    <x-icon-btn icon="arrow_downward" tone="outline" size="md" href="#funktionen" show-label>{{ __('Funktionen ansehen') }}</x-icon-btn>
                </div>
            </section>

            {{-- Feature-Übersicht --}}
            <section id="funktionen" class="mt-16 scroll-mt-24">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Funktionen') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Alles, was der Betrieb braucht') }}</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-base text-base-content/70">{{ __('Vom ersten Eintrag im Feld bis zur fertigen Rechnung – durchgängig in einer Oberfläche.') }}</p>
                </div>

                <div class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as $feature)
                        <article class="group rounded-box border border-base-300 bg-base-100 p-6 shadow-xs transition hover:border-primary/40 hover:shadow-sm">
                            <div class="flex size-12 items-center justify-center rounded-box bg-primary/10 text-primary transition group-hover:bg-primary/15">
                                <x-icon :name="$feature['icon']" size="1.6rem" />
                            </div>
                            <h3 class="mt-4 font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ $feature['title'] }}</h3>
                            <p class="mt-2 text-sm text-base-content/70">{{ $feature['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            {{-- So arbeitest du --}}
            <section class="mt-16 rounded-box border border-base-300 bg-base-100 p-8 shadow-xs sm:p-10">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Workflow') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('So arbeitest du damit') }}</h2>
                </div>

                <div class="mt-9 grid gap-6 md:grid-cols-3">
                    @foreach ($steps as $index => $step)
                        <div class="relative rounded-box border border-base-300 bg-base-200/60 p-6">
                            <span class="absolute -top-3 left-6 flex size-7 items-center justify-center rounded-full bg-primary font-['Space_Grotesk'] text-sm font-bold text-primary-content">{{ $index + 1 }}</span>
                            <div class="flex items-center gap-3 pt-1">
                                <x-icon :name="$step['icon']" size="1.4rem" class="text-primary" />
                                <h3 class="font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ $step['title'] }}</h3>
                            </div>
                            <p class="mt-3 text-sm text-base-content/70">{{ $step['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="border-t border-base-300 bg-base-100">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-6 py-6 text-sm text-base-content/70 sm:flex-row lg:px-10">
                <span><x-footer-copyright /></span>
                {{-- TODO: Öffentliche Impressum-/Datenschutz-Seiten anlegen und hier verlinken (aktuell nur admin.privacy.index hinter Login). --}}
                <nav class="flex items-center gap-5">
                    <a href="#" class="transition hover:text-base-content">{{ __('Impressum') }}</a>
                    <a href="#" class="transition hover:text-base-content">{{ __('Datenschutz') }}</a>
                </nav>
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
