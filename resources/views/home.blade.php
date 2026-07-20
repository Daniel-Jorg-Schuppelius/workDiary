<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Anti-Flash-Theme (ein Partial statt 17 Kopien; Vollaudit 2026-07, M51). --}}
        @include('partials.theme-bootstrap')
        <title>{{ config('app.name', 'WorkDiary') }}</title>

        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/logo/workdiary-mark-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/logo/workdiary-mark-192.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo/workdiary-mark-192.png') }}">

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
            $registrationEnabled = (bool) config('app.registration_enabled');

            // Funktionsgruppen für die Übersicht – Icons spiegeln die Hauptnavigation.
            $features = [
                ['icon' => 'list_alt',       'title' => __('Arbeitstagebuch & Aufgaben'),   'text' => __('Vorgänge erfassen und in Arbeitsliste, Kanban und Wochenansicht behalten – inklusive Dienstplänen.')],
                ['icon' => 'schedule',       'title' => __('Zeit & Schicht'),               'text' => __('Stempeluhr, Terminals, Schichtpläne, Stundenzettel und Arbeitszeitkonto – auf Wunsch standortbasiert per Geofence.')],
                ['icon' => 'directions_car', 'title' => __('Touren & Fahrzeuge'),           'text' => __('Touren planen, Fahrtenbuch führen, Fahrzeuge sowie Tank- und Ladelogs verwalten.')],
                ['icon' => 'folder_special', 'title' => __('Kunden & Projekte'),            'text' => __('Kunden, Lieferanten und Projekte organisieren – mit agilen Boards, Backlog und Sprints.')],
                ['icon' => 'receipt_long',   'title' => __('Rechnungen & Finanzen'),        'text' => __('Spesen, Angebote und die Belegkette von Abschlag bis Schlussrechnung – mit E-Rechnung (XRechnung/ZUGFeRD) und Kassenbuch.')],
                ['icon' => 'inventory_2',    'title' => __('Lager & Fertigung'),            'text' => __('Artikelstamm, Bestände mit Seriennummern, Inventur, Beschaffung und Fertigung mit Stücklisten.')],
                ['icon' => 'construction',   'title' => __('Bau & GAEB'),                   'text' => __('GAEB-Leistungsverzeichnisse importieren, Aufmaße erfassen und Nachträge sauber abrechnen.')],
                ['icon' => 'support_agent',  'title' => __('Helpdesk & Service'),           'text' => __('Tickets mit SLA und Omnichannel-Eingang, Servicekatalog und Wissensbasis fürs Team.')],
                ['icon' => 'groups',         'title' => __('Team & Personal'),              'text' => __('Mitarbeiter, Teams, Urlaub, Krankmeldungen, Qualifikationen, Lohn und Bewerbungen im Blick.')],
                ['icon' => 'policy',         'title' => __('Compliance & Datenschutz'),     'text' => __('Hinweisgebersystem nach HinSchG, Datenschutzmanagement, ISMS und Krisenmanagement.')],
                ['icon' => 'handyman',       'title' => __('Assets & Verleih'),             'text' => __('Geräteverleih, Leasing- und Vertragsakten, Prüfmittel und Kalibrierung – inklusive Einsatzsperren.')],
                ['icon' => 'lightbulb',      'title' => __('Ideen, Wissen & KI'),           'text' => __('Ideenlandkarten, Wissensbasis, Dokumente und Team-Chat – plus optionale KI-Vorschläge, Übernahme immer per Klick.')],
            ];

            // Querschnitts-Eigenschaften der Plattform (Sicherheit, Nachvollziehbarkeit, Reichweite).
            $platform = [
                ['icon' => 'vpn_key',             'title' => __('SSO & 2FA'),        'text' => __('Single-Sign-on über OIDC und SAML, SCIM-Provisionierung, Zwei-Faktor mit TOTP oder Passkey.')],
                ['icon' => 'verified',            'title' => __('GoBD & Audit'),     'text' => __('Revisionssichere Änderungshistorie, Kassenbuch mit Tagesabschluss und GDPdU/Z3-Export.')],
                ['icon' => 'enhanced_encryption', 'title' => __('Verschlüsselung'),  'text' => __('Sensible Daten werden verschlüsselt gespeichert – Hinweisgeber-Fälle mit eigenem Schlüssel je Fall.')],
                ['icon' => 'cloud_off',           'title' => __('Offline-fähig'),    'text' => __('Im Einsatz ohne Empfang weiterarbeiten – Änderungen synchronisieren automatisch nach.')],
                ['icon' => 'translate',           'title' => __('Fünf Sprachen'),    'text' => __('Deutsch, Englisch, Französisch, Italienisch und Spanisch – durchgängig übersetzt, inklusive Hilfe.')],
                ['icon' => 'domain',              'title' => __('Branchenprofile'),  'text' => __('Vorkonfigurierte Profile von Handwerk und Bau über Facility bis Pflege und IT.')],
            ];

            // Produktnamen der angebundenen Systeme – bewusst unübersetzt.
            $integrations = [
                'DATEV', 'Lexoffice', 'JTL-Wawi', 'orgaMAX', 'OpenProject', 'Todoist',
                'Toggl', 'Kimai', 'Clockify', 'Zammad', 'Nextcloud', 'CalDAV', 'WebDAV',
                'Microsoft Teams', 'Mattermost', 'DHL', 'OwnTracks', 'Traccar', 'IMAP', 'CTI',
            ];

            $steps = [
                ['icon' => 'edit_note',       'title' => __('Erfassen'),  'text' => __('Arbeitszeiten, Vorgänge, Touren und Spesen direkt im Einsatz dokumentieren.')],
                ['icon' => 'event_available', 'title' => __('Planen'),    'text' => __('Schichten, Dienstpläne und Projekte koordinieren – das ganze Team synchron.')],
                ['icon' => 'request_quote',   'title' => __('Abrechnen'), 'text' => __('Stundenzettel, Belege und Rechnungen sauber auswerten und abrechnen.')],
            ];
        @endphp

        {{-- Header folgt der App-Header-Anatomie (layouts/app): sticky, opakes
             bg-base-100, border-b + shadow-xs, min-h-14, Logo h-9, Aktionen
             rechts als freie Buttons (keine Pillen-Box). --}}
        <header class="sticky top-0 z-50 border-b border-base-300 bg-base-100 shadow-xs">
            <div class="flex min-h-14 w-full items-center justify-between gap-4 px-3 py-2 xl:px-4">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2">
                    <img src="{{ asset('img/logo/workdiary-logo-512.png') }}" alt="WorkDiary"
                         class="h-9 w-auto max-w-40 shrink-0 object-contain">
                </a>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <span data-theme-label class="material-symbols-outlined text-base leading-none">dark_mode</span>
                    </button>
                    <x-locale-switcher />
                    <x-icon-btn icon="login" tone="primary" size="sm" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                </div>
            </div>
        </header>

        {{-- Gast-Wrapperbreite wie im App-Layout: max-w-screen-2xl + px-Staffel. --}}
        <main class="mx-auto w-full max-w-screen-2xl px-2 pb-20 pt-8 sm:px-4 xl:px-8 2xl:px-12">
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
                    {{ __('Von der Zeiterfassung im Einsatz bis zu Rechnung, Lager und Compliance – ein Werkzeug für den ganzen Betrieb.') }}
                </p>

                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <x-icon-btn icon="login" tone="primary" size="md" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                    @if ($registrationEnabled)
                        <x-icon-btn icon="app_registration" tone="secondary" size="md" :href="route('register')" show-label>{{ __('Organisation registrieren') }}</x-icon-btn>
                    @endif
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

                <div class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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

            {{-- Plattform-Eigenschaften --}}
            <section class="mt-16 rounded-box border border-base-300 bg-base-100 p-8 shadow-xs sm:p-10">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Plattform') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Sicher, nachvollziehbar, einsatzbereit') }}</h2>
                </div>

                <div class="mt-9 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($platform as $item)
                        <div class="flex items-start gap-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary">
                                <x-icon :name="$item['icon']" size="1.3rem" />
                            </div>
                            <div>
                                <h3 class="font-['Space_Grotesk'] text-base font-semibold text-base-content">{{ $item['title'] }}</h3>
                                <p class="mt-1 text-sm text-base-content/70">{{ $item['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Integrationen --}}
            <section class="mt-16 text-center">
                <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Integrationen') }}</div>
                <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Spricht mit euren Systemen') }}</h2>
                <p class="mx-auto mt-3 max-w-2xl text-base text-base-content/70">{{ __('Buchhaltung, Cloud-Speicher, Aufgaben- und Ticketsysteme anbinden – Import-Drehscheibe, REST-API und Webhooks inklusive.') }}</p>

                <div class="mx-auto mt-8 flex max-w-4xl flex-wrap items-center justify-center gap-2">
                    @foreach ($integrations as $integration)
                        <span class="badge badge-ghost badge-lg border border-base-300 bg-base-100">{{ $integration }}</span>
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

        {{-- Footer folgt der App-Footer-Anatomie (layouts/app): fix am unteren
             Rand, h-12, zentrierter Kompakt-Inhalt (mobil gestapelt). Statt
             Version/Build-Hash (nur eingeloggt relevant) tragen Gast-Seiten
             die Pflicht-Links Impressum/Datenschutz. --}}
        <footer class="fixed inset-x-0 bottom-0 z-50 h-12 border-t border-base-300 bg-base-100 shadow-xs">
            <div class="mx-auto flex h-full w-full max-w-screen-2xl flex-col items-center justify-center gap-0 px-4 text-center text-[0.65rem] leading-tight text-base-content/70 sm:flex-row sm:gap-4 sm:text-xs xl:px-8 2xl:px-12">
                <div class="max-w-full"><x-footer-copyright /></div>
                <nav class="flex items-center gap-4">
                    <a href="{{ route('legal.imprint') }}" class="transition hover:text-base-content">{{ __('Impressum') }}</a>
                    <a href="{{ route('legal.privacy') }}" class="transition hover:text-base-content">{{ __('Datenschutz') }}</a>
                </nav>
            </div>
        </footer>

        {{-- Theme-Toggle wird zentral von resources/js/layout.js (in app.js gebündelt)
             gesteuert. Ein zusätzliches Inline-Script hier würde einen ZWEITEN
             Click-Handler an denselben Button hängen → der Klick schaltet doppelt
             um und das Theme bleibt scheinbar stehen. Das Anti-Flash-Skript im
             <head> setzt nur das initiale Theme; den Umschalter macht layout.js. --}}
    </body>
</html>
