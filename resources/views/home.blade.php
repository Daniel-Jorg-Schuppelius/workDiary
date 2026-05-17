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
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|ibm-plex-sans:400,500,600" rel="stylesheet" />
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
    <body class="min-h-screen bg-primary-content text-base-content">
        @php
            $priorityEntries = $entries
                ->filter(fn ($entry) => in_array((int) $entry->gelesen, [2, 3], true))
                ->sortByDesc(fn ($entry) => (int) $entry->gelesen)
                ->take(5);
            $currentMode = $currentMode ?? session('work_mode', 'legacy');
            $indexRoute = $currentMode === 'legacy' ? 'legacy.diary.index' : 'duties.index';
            $createRoute = $currentMode === 'legacy' ? 'legacy.diary.create' : 'diary.create';
        @endphp
        <header class="fixed inset-x-0 top-0 z-50 border-b border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-between gap-4 px-6 py-3 xl:px-8 2xl:px-12">
                <a href="{{ route('home') }}" class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary">WorkDiary</a>
                <div class="ml-auto flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="text-base leading-none">◐</span>
                    </button>
                    @auth
                        <a href="{{ route($indexRoute) }}" class="btn btn-sm btn-ghost">▤ Arbeitsliste</a>
                        <a href="{{ route($createRoute) }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Eintrag') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-sm btn-primary">⇢ Anmelden</a>
                    @endauth
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-6 pb-20 pt-24 lg:px-10">
            <header class="mb-6 rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="font-['Space_Grotesk'] text-sm uppercase tracking-[0.35em] text-primary">WorkDiary</p>
                        <h1 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content md:text-4xl">{{ __('Operations Dashboard') }}</h1>
                        <p class="mt-3 max-w-3xl text-base text-base-content/80">Zentrale Arbeitsoberfläche für Teamstatus, offene Punkte und direkte Aktionen im Tagesgeschäft.</p>
                        @auth
                            <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-base-300 bg-base-200 px-3 py-1.5 text-xs uppercase tracking-[0.24em] text-base-content/80">
                                <span>{{ __('Aktiver Modus') }}</span>
                                <span class="badge badge-sm {{ $currentMode === 'legacy' ? 'badge-warning' : 'badge-primary' }}">{{ $currentMode === 'legacy' ? __('Legacy') : __('Neu') }}</span>
                            </div>
                        @endauth
                        <div class="mt-5 flex flex-wrap gap-3">
                            @auth
                                <a href="{{ route($createRoute) }}" data-entry-modal-trigger class=" btn btn-sm btn-primary"><x-icon name="add" /> Neuer Eintrag</a>
                                <a href="{{ route($indexRoute) }}" class=" btn btn-sm btn-outline"><x-icon name="list" /> Arbeitsliste öffnen</a>
                                <a href="{{ route($indexRoute, ['status' => 3]) }}" class=" btn btn-sm btn-error btn-outline"><x-icon name="warning" /> Probleme priorisieren</a>
                            @else
                                <a href="{{ route('login') }}" class=" btn btn-sm btn-primary"><x-icon name="login" /> Anmelden</a>
                            @endauth
                        </div>
                    </div>

                    <div class="grid gap-3 text-sm text-base-content/80 md:min-w-80">
                        <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs">
                            <p class="font-medium text-base-content">{{ __('Datenquelle') }}</p>
                            <p class="mt-1 text-base-content/80">
                                @if ($legacyOnline)
                                    Live verbunden mit Legacy-Datenbank.
                                @elseif ($legacyConfigured)
                                    Konfiguriert, aktuell nicht erreichbar.
                                @else
                                    Noch nicht konfiguriert.
                                @endif
                            </p>
                        </div>
                        @auth
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button class=" btn btn-sm btn-outline w-full justify-start">⎋ {{ Auth::user()->name }} {{ __('abmelden') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </header>

            <main class="grid flex-1 gap-6 lg:grid-cols-[1.35fr_0.9fr]">
                <section class="space-y-6">
                    @if ($canViewSensitive)
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <article class="rounded-[1.75rem] border border-base-300 bg-base-100 p-5 shadow-xs">
                                <p class="badge badge-ghost badge-sm">{{ __('Einträge') }}</p>
                                <p class="mt-3 font-['Space_Grotesk'] text-4xl font-bold text-base-content">{{ number_format($stats['entries_total'], 0, ',', '.') }}</p>
                                <p class="mt-2 text-sm text-base-content/70">{{ __('Gesamtbestand im Legacy-Tagebuch') }}</p>
                            </article>
                            <article class="rounded-[1.75rem] border border-base-300 bg-base-100 p-5 shadow-xs">
                                <p class="badge badge-warning badge-sm">{{ __('Offen') }}</p>
                                <p class="mt-3 font-['Space_Grotesk'] text-4xl font-bold text-base-content">{{ number_format($stats['entries_open'], 0, ',', '.') }}</p>
                                <p class="mt-2 text-sm text-base-content/75">{{ __('Sofort in Bearbeitung nehmen') }}</p>
                            </article>
                            <article class="rounded-[1.75rem] border border-base-300 bg-base-100 p-5 shadow-xs">
                                <p class="badge badge-error badge-sm">{{ __('Probleme') }}</p>
                                <p class="mt-3 font-['Space_Grotesk'] text-4xl font-bold text-base-content">{{ number_format($stats['entries_alert'], 0, ',', '.') }}</p>
                                <p class="mt-2 text-sm text-base-content/75">{{ __('Eskalationen mit Handlungsbedarf') }}</p>
                            </article>
                            <article class="rounded-[1.75rem] border border-base-300 bg-base-100 p-5 shadow-xs">
                                <p class="badge badge-primary badge-sm">{{ __('Team') }}</p>
                                <p class="mt-3 font-['Space_Grotesk'] text-4xl font-bold text-base-content">{{ number_format($stats['team_size'], 0, ',', '.') }}</p>
                                <p class="mt-2 text-sm text-base-content/75">{{ __('Verfügbare Mitarbeitende') }}</p>
                            </article>
                        </div>

                        <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <div class="badge badge-error badge-sm">{{ __('Prioritäten') }}</div>
                                    <p class="mt-2 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Jetzt wichtig') }}</p>
                                    <p class="mt-1 text-sm text-base-content/75">{{ __('Problem- und offene Einträge als direkte Arbeitsliste.') }}</p>
                                </div>
                                <a href="{{ route($indexRoute, ['status' => 3]) }}" class="btn btn-error btn-outline btn-sm">⚠ Nur Probleme anzeigen</a>
                            </div>

                            <div class="mt-5 space-y-3">
                                @forelse ($priorityEntries as $entry)
                                    <article class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span @class([
                                                'badge badge-sm',
                                                'badge-warning' => (int) $entry->gelesen === 2,
                                                'badge-error' => (int) $entry->gelesen === 3,
                                            ])>{{ $entry->statusLabel() }}</span>
                                            <span class="text-sm text-base-content/70">{{ optional($entry->author)->uname ?? __('Unbekannt') }}</span>
                                        </div>
                                        <p class="mt-3 text-base-content">{{ truncate($entry->inhalt ?? 'Ohne Beschreibung', 180) }}</p>
                                        <div class="mt-2 text-sm text-base-content/70">{{ __('Von') }} {{ optional($entry->von)?->format('d.m.Y H:i') ?? __('offen') }} · {{ __('Bis') }} {{ optional($entry->bis)?->format('d.m.Y H:i') ?? __('offen') }}</div>
                                    </article>
                                @empty
                                    <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-5 text-base-content/80">{{ __('Keine priorisierten Einträge gefunden.') }}</div>
                                @endforelse
                            </div>
                        </section>

                        <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                            <div class="flex flex-col gap-3 border-b border-base-300 pb-5 md:flex-row md:items-end md:justify-between">
                                <div>
                                    <p class="font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Aktuelle Arbeitslage') }}</p>
                                    <p class="mt-2 text-sm text-base-content/70">{{ __('Neueste Einträge aus dem laufenden Betrieb.') }}</p>
                                </div>
                                <div class="badge badge-ghost badge-lg px-4 py-3 text-xs uppercase tracking-[0.3em] text-base-content/70">
                                    Produktiver Betrieb
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                @forelse ($entries as $entry)
                                    <article class="grid gap-4 rounded-[1.4rem] border border-base-300 bg-base-100 p-4 shadow-xs transition hover:border-primary/30 md:grid-cols-[1fr_auto] md:items-center">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <span @class([
                                                    'badge badge-sm',
                                                    'badge-success' => $entry->statusTone() === 'done',
                                                    'badge-info' => $entry->statusTone() === 'progress',
                                                    'badge-warning' => $entry->statusTone() === 'open',
                                                    'badge-error' => $entry->statusTone() === 'alert',
                                                    'badge-ghost' => $entry->statusTone() === 'neutral',
                                                ])>{{ $entry->statusLabel() }}</span>
                                                <span class="text-sm text-base-content/70">{{ optional($entry->author)->uname ?? __('Unbekannt') }}</span>
                                            </div>
                                            <p class="mt-3 text-lg font-semibold text-base-content">{{ truncate($entry->inhalt ?? 'Ohne Beschreibung', 140) }}</p>
                                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-base-content/70">
                                                <span>{{ __('Von') }} {{ optional($entry->von)?->format('d.m.Y H:i') ?? __('offen') }}</span>
                                                <span>{{ __('Bis') }} {{ optional($entry->bis)?->format('d.m.Y H:i') ?? __('offen') }}</span>
                                                <span>Aktualisiert {{ optional($entry->aktuell)?->diffForHumans() ?? 'unbekannt' }}</span>
                                            </div>
                                        </div>
                                        <div class="rounded-box border border-base-300 bg-base-200 px-4 py-3 text-sm text-base-content/70 md:max-w-56">
                                            {{ truncate($entry->antwort ?: 'Noch keine Rückmeldung im Altsystem.', 110) }}
                                        </div>
                                    </article>
                                @empty
                                    <div class="rounded-[1.4rem] border border-dashed border-base-300 bg-base-100 p-6 text-base-content/70">
                                        Noch keine Legacy-Daten sichtbar. Sobald LEGACY_DB_* gesetzt ist, liest die neue App direkt aus den vorhandenen Tabellen.
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    @else
                        <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                            <p class="font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Produktzugang erforderlich') }}</p>
                                <p class="mt-3 text-base-content/80">Diese Oberfläche ist ein Arbeitsprodukt. Operative Inhalte, Kennzahlen und Teamdaten sind nur nach Anmeldung sichtbar.</p>
                            <div class="mt-4"><a href="{{ route('login') }}" class=" btn btn-sm btn-primary">⇢ Jetzt anmelden</a></div>
                        </section>
                    @endif
                </section>

                <aside class="space-y-6">
                    <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                        <p class="font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Heute arbeiten') }}</p>
                        <div class="mt-5 space-y-4">
                            <div class="rounded-[1.4rem] border border-base-300 bg-base-200 p-4">
                                <p class="font-semibold text-base-content">1. Offene Punkte priorisieren</p>
                                <p class="mt-2 text-sm text-base-content/75">{{ __('Arbeitsliste filtern und zuerst kritische Probleme bearbeiten.') }}</p>
                            </div>
                            <div class="rounded-[1.4rem] border border-base-300 bg-base-200 p-4">
                                <p class="font-semibold text-base-content">2. Neue Einträge dokumentieren</p>
                                <p class="mt-2 text-sm text-base-content/75">{{ __('Vorgänge sauber erfassen, Zeitraum und Status direkt setzen.') }}</p>
                            </div>
                            <div class="rounded-[1.4rem] border border-base-300 bg-base-200 p-4">
                                <p class="font-semibold text-base-content">3. Rückmeldungen nachziehen</p>
                                <p class="mt-2 text-sm text-base-content/75">{{ __('Offene Antworten finalisieren und auf erledigt setzen.') }}</p>
                            </div>
                            @auth
                                <a href="{{ route($indexRoute, ['status' => 2]) }}" class=" btn btn-sm btn-outline w-full">↗ Zu offenen Aufgaben</a>
                            @endif
                        </div>
                    </section>

                    @if ($canViewSensitive)
                        <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                            <div class="flex items-center justify-between gap-4">
                                <p class="font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Team') }}</p>
                                <span class="badge badge-ghost">{{ $team->count() }} sichtbar</span>
                            </div>
                            <div class="mt-5 space-y-3">
                                @forelse ($team as $member)
                                    <div class="rounded-[1.25rem] border border-base-300 bg-base-200 px-4 py-3">
                                        <p class="font-semibold text-base-content">{{ $member->uname }}</p>
                                        <p class="mt-1 text-sm text-base-content/70">{{ $member->email ?: 'Keine E-Mail im Altbestand' }}</p>
                                    </div>
                                @empty
                                    <div class="rounded-[1.25rem] border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/70">{{ __('Teamdaten werden eingeblendet, sobald die Legacy-DB erreichbar ist.') }}</div>
                                @endforelse
                            </div>
                        </section>
                    @endif
                </aside>
            </main>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-6 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                &copy; {{ date('Y') }} WorkDiary. Alle Rechte vorbehalten.
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
