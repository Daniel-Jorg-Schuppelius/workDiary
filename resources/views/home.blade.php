{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : home.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim" class="motion-safe:scroll-smooth">
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

            $sections = [
                'funktionen' => __('Funktionen'),
                'branchen' => __('Branchen'),
                'integrationen' => __('Integrationen'),
                'plattform' => __('Plattform'),
            ];

            // Funktionsgruppen: Ton je Gruppe, Icons spiegeln die Hauptnavigation.
            // Klassen stehen ausgeschrieben, damit Tailwind sie beim Scan findet.
            $featureGroups = [
                [
                    'title' => __('Im Einsatz'),
                    'dot' => 'bg-primary',
                    'tile' => 'bg-primary/10 text-primary group-hover:bg-primary/15',
                    'hover' => 'hover:border-primary/40',
                    'items' => [
                        ['icon' => 'list_alt',        'title' => __('Auftragsbuch & Aufgaben'), 'text' => __('Vorgänge erfassen, offene Punkte als Folgeauftrag weitergeben und alles in Arbeitsliste, Kanban und Wochenansicht behalten – inklusive Bereitschaft.')],
                        ['icon' => 'schedule',        'title' => __('Zeit & Anwesenheit'),      'text' => __('Stempeluhr, Terminals, Stundenzettel und Zeitkonten, auf Wunsch per Geofence – geprüft nach Arbeitszeitgesetz, Mindestlohn sowie Lenk- und Ruhezeiten.')],
                        ['icon' => 'event_available', 'title' => __('Planung & Disposition'),   'text' => __('Schicht- und Dienstpläne, Disposition mit Vorschlägen für Leerzeiten, Termine mit Kalender-Abo und Wächterrundgänge mit Kontrollpunkten.')],
                        ['icon' => 'fact_check',      'title' => __('Protokolle & Formulare'),  'text' => __('Abnahme- und Prüfprotokolle mit Fotos, Checklisten und Unterschrift, eigene Formulare und Schritt-für-Schritt-Prozeduren – auch mobil vor Ort.')],
                        ['icon' => 'directions_car',  'title' => __('Fuhrpark & Fahrtenbuch'),  'text' => __('Unveränderbares Fahrtenbuch mit Fahrersignatur, Touren, Tank- und Ladelogs, Führerscheinkontrolle – fällige Fristen sperren das Fahrzeug automatisch.')],
                    ],
                ],
                [
                    'title' => __('Kunden & Finanzen'),
                    'dot' => 'bg-secondary',
                    'tile' => 'bg-secondary/10 text-secondary group-hover:bg-secondary/15',
                    'hover' => 'hover:border-secondary/40',
                    'items' => [
                        ['icon' => 'storefront',      'title' => __('Kunden, Vertrieb & Portal'), 'text' => __('Leads, Angebote mit Nachfassen und Provisionen – dazu ein Kundenportal mit Online-Terminbuchung, Rundschreiben, Umfragen und Vereinbarungen zum Unterschreiben.')],
                        ['icon' => 'folder_special',  'title' => __('Projekte & Agil'),           'text' => __('Projekte mit Meilensteinen, Aufgaben und Wirtschaftlichkeit im Blick – agil mit Backlog, Sprints und Boards.')],
                        ['icon' => 'receipt_long',    'title' => __('Faktura & E-Rechnung'),      'text' => __('Angebote, freie Rechnungen und die Belegkette bis zur Schlussrechnung – als XRechnung, ZUGFeRD oder über Peppol, mit Mahnlauf und Verbrauchsabrechnung.')],
                        ['icon' => 'account_balance', 'title' => __('Buchhaltung & Finanzen'),    'text' => __('Spesen und Kassenbuch, lokale Buchhaltung mit Kontoauszug-Import und SEPA, BWA und Kostenstellen, Liquiditätsvorschau und AfA – oder Übergabe an DATEV.')],
                        ['icon' => 'support_agent',   'title' => __('Helpdesk & Service'),        'text' => __('Tickets mit SLA und Omnichannel-Eingang, ITSM-Service-Desk, Reklamationen, Schadensfälle mit Regulierung und Fernwartungssitzungen.')],
                    ],
                ],
                [
                    'title' => __('Material & Objekte'),
                    'dot' => 'bg-accent',
                    'tile' => 'bg-accent/10 text-accent group-hover:bg-accent/15',
                    'hover' => 'hover:border-accent/40',
                    'items' => [
                        ['icon' => 'inventory_2',  'title' => __('Lager, Einkauf & Versand'),      'text' => __('Artikel mit Varianten, Bestände mit Seriennummern, mobile Inventur, Beschaffung, Fertigung mit Stücklisten und Versand über DHL, UPS oder FedEx.')],
                        ['icon' => 'construction', 'title' => __('Bau & GAEB'),                    'text' => __('GAEB-Leistungsverzeichnisse, Aufmaß und Nachträge, e-Vergabe, Kostenermittlung nach DIN 276, Bürgschaften und Sicherheitseinbehalte – plus Bautagebuch mit Wetterdaten.')],
                        ['icon' => 'handyman',     'title' => __('Anlagen, Verleih & Prüfmittel'), 'text' => __('Anlagen mit Wartung und Zählern, Geräteverleih, Leasing- und Vertragsakten mit Indexanpassung, Prüfmittel mit Kalibrierung – inklusive Einsatzsperren.')],
                        ['icon' => 'apartment',    'title' => __('Liegenschaften & Zutritt'),      'text' => __('Standorte, Gebäude, Etagen und Räume, Schlüssel- und Transponderausgabe mit Nachweis sowie Energie- und Zählerstände.')],
                        ['icon' => 'eco',          'title' => __('Nachhaltigkeit & Entsorgung'),   'text' => __('ESG-Bewertung und CO₂-Bilanz, Fußabdruck je Produkt, geprüfte Umweltaussagen sowie Altgeräte-Rücknahme mit Entsorgungsnachweis.')],
                    ],
                ],
                [
                    'title' => __('Menschen & Wissen'),
                    'dot' => 'bg-info',
                    'tile' => 'bg-info/10 text-info group-hover:bg-info/15',
                    'hover' => 'hover:border-info/40',
                    'items' => [
                        ['icon' => 'badge',     'title' => __('Personal & Arbeitsschutz'), 'text' => __('Digitale Personalakte, Qualifikationen, Urlaub, Lohn, Bewerbungen und Austritt – dazu Gefährdungsbeurteilungen, Unterweisungen und Vorsorge.')],
                        ['icon' => 'school',    'title' => __('Lernplattform'),            'text' => __('Eigene Kurse mit Prüfungen, Lernpfaden und Zertifikaten, Präsenztermine und Video mit Untertiteln – SCORM, xAPI, cmi5 und LTI 1.3 inklusive.')],
                        ['icon' => 'policy',    'title' => __('Compliance & Datenschutz'), 'text' => __('Hinweisgebersystem nach HinSchG, Datenschutzmanagement mit Auskunft, ISMS nach ISO 27001 und Krisenmanagement mit Krisenraum.')],
                        ['icon' => 'lightbulb', 'title' => __('Wissen, Ideen & KI'),       'text' => __('Wissenssammlungen, Suche über erledigte Arbeit, Ideenlandkarten, Dokumente und Team-Chat – plus KI-Assistenz mit Anbieter Ihrer Wahl, Übernahme immer per Klick.')],
                        ['icon' => 'groups',    'title' => __('Vereinsverwaltung'),        'text' => __('Mitglieder ohne Loginpflicht, Gruppen, Training und Anwesenheit, Beiträge mit Familienkonten – optional Graduierungen, Mannschaften und Wettkämpfe.')],
                    ],
                ],
            ];

            // Je Profil unter database/data/branchprofiles ein Eintrag.
            $branches = [
                ['icon' => 'handyman',                'label' => __('Handwerk & Service')],
                ['icon' => 'foundation',              'label' => __('Bau & Ausbau')],
                ['icon' => 'electrical_services',     'label' => __('Elektro')],
                ['icon' => 'plumbing',                'label' => __('Sanitär, Heizung, Klima')],
                ['icon' => 'park',                    'label' => __('Garten- & Landschaftsbau')],
                ['icon' => 'cleaning_services',       'label' => __('Gebäudereinigung')],
                ['icon' => 'apartment',               'label' => __('Facility Management')],
                ['icon' => 'precision_manufacturing', 'label' => __('Maschinen- & Anlagenwartung')],
                ['icon' => 'car_repair',              'label' => __('Kfz- & Fuhrparkservice')],
                ['icon' => 'local_shipping',          'label' => __('Spedition & Logistik')],
                ['icon' => 'local_taxi',              'label' => __('Taxi & Mietwagen')],
                ['icon' => 'security',                'label' => __('Sicherheitsdienst')],
                ['icon' => 'medical_services',        'label' => __('Ambulante Pflege')],
                ['icon' => 'computer',                'label' => __('IT-Service')],
                ['icon' => 'calculate',               'label' => __('Steuerberatung')],
                ['icon' => 'print',                   'label' => __('Druck- & Kopiershop')],
                ['icon' => 'restaurant',              'label' => __('Partyservice & Catering')],
                ['icon' => 'celebration',             'label' => __('Veranstalter')],
                ['icon' => 'speaker',                 'label' => __('Veranstaltungstechnik')],
                ['icon' => 'sports_soccer',           'label' => __('Sportverein')],
            ];

            // Produktnamen der angebundenen Systeme – bewusst unübersetzt.
            $integrations = [
                ['icon' => 'account_balance', 'title' => __('Buchhaltung & E-Rechnung'), 'items' => ['DATEV', 'Lexoffice', 'sevDesk', 'easybill', 'BuchhaltungsButler', 'orgaMAX', 'Peppol']],
                ['icon' => 'local_shipping',  'title' => __('Handel & Versand'),         'items' => ['JTL-Wawi', 'Billbee', 'Etsy', 'DHL', 'UPS', 'FedEx']],
                ['icon' => 'task_alt',        'title' => __('Zeit, Aufgaben & Tickets'), 'items' => ['Toggl', 'Clockify', 'Kimai', 'Todoist', 'OpenProject', 'GitHub', 'GitLab', 'Zammad']],
                ['icon' => 'cloud',           'title' => __('Kalender & Cloud'),         'items' => ['Microsoft 365', 'SharePoint', 'Google Calendar', 'Google Drive', 'Dropbox', 'Nextcloud', 'Calendly', 'CalDAV', 'CardDAV', 'WebDAV', 'S3']],
                ['icon' => 'forum',           'title' => __('Kommunikation & Ortung'),   'items' => ['Microsoft Teams', 'Mattermost', 'sipgate', 'FRITZ!Box', 'seven.io', 'IMAP', 'TeamViewer', 'AnyDesk', 'OwnTracks', 'Traccar']],
                ['icon' => 'auto_awesome',    'title' => __('KI & Übersetzung'),         'items' => ['OpenAI', 'Anthropic', 'Gemini', 'Azure OpenAI', 'Ollama', 'DeepL', 'Azure Translator', 'LibreTranslate']],
            ];

            $standards = ['XRechnung', 'ZUGFeRD', 'GAEB', 'DATANORM', 'openTRANS', 'OCI', 'SEPA', 'CAMT', 'MT940', 'iCalendar', 'SCORM', 'xAPI', 'cmi5', 'LTI 1.3'];

            // Querschnitts-Eigenschaften der Plattform (Sicherheit, Nachvollziehbarkeit, Betrieb).
            $platform = [
                ['icon' => 'vpn_key',             'title' => __('SSO & 2FA'),               'text' => __('Single-Sign-on über OIDC und SAML, SCIM-Provisionierung, Zwei-Faktor mit TOTP oder Passkey.')],
                ['icon' => 'verified',            'title' => __('GoBD & Audit'),            'text' => __('Revisionssichere Änderungshistorie mit Hash-Kette, Verfahrensdokumentation, Prüferzugang und GDPdU/Z3-Export.')],
                ['icon' => 'enhanced_encryption', 'title' => __('Verschlüsselung'),         'text' => __('Sensible Daten werden verschlüsselt gespeichert – Hinweisgeber-Fälle mit eigenem Schlüssel je Fall.')],
                ['icon' => 'shield_person',       'title' => __('Ihre Daten bleiben Ihre'), 'text' => __('Keine Datenverkäufe, keine versteckte Weitergabe – mit Löschkonzept und DSGVO-Auskunft auf Knopfdruck.')],
                ['icon' => 'cloud_off',           'title' => __('Offline-fähig'),           'text' => __('Im Einsatz ohne Empfang weiterarbeiten – Änderungen synchronisieren automatisch nach.')],
                ['icon' => 'translate',           'title' => __('Fünf Sprachen'),           'text' => __('Deutsch, Englisch, Französisch, Italienisch und Spanisch – durchgängig übersetzt, inklusive Hilfe.')],
                ['icon' => 'extension',           'title' => __('Modular'),                 'text' => __('Nur freischalten, was der Betrieb braucht – Arbeitsbereiche und Funktionsprofile halten die Oberfläche schlank.')],
                ['icon' => 'tune',                'title' => __('Anpassbar ohne Code'),     'text' => __('Eigene Felder für Kunden, Aufträge, Anlagen, Artikel und Projekte, dazu Automationsregeln und Frühwarnungen.')],
                ['icon' => 'dns',                 'title' => __('Betrieb nach Wahl'),       'text' => __('Auf eigenem Server, als Private Cloud oder gehostet – Backups verschlüsselt in Ihren eigenen Cloudspeicher.')],
            ];

            $steps = [
                ['icon' => 'event_available', 'title' => __('Planen'),    'text' => __('Schichten, Dienstpläne und Projekte koordinieren – das ganze Team synchron.')],
                ['icon' => 'edit_note',       'title' => __('Erfassen'),  'text' => __('Zeiten, Vorgänge, Protokolle und Spesen direkt im Einsatz dokumentieren – auch offline.')],
                ['icon' => 'request_quote',   'title' => __('Abrechnen'), 'text' => __('Leistungen in Rechnungen übernehmen, als E-Rechnung versenden und Zahlungen abgleichen.')],
                ['icon' => 'monitoring',      'title' => __('Auswerten'), 'text' => __('BWA, Liquidität, Projektwirtschaftlichkeit und Team-Auswertungen als Grundlage für Entscheidungen.')],
            ];

            $stats = [
                ['value' => $moduleCount,                                                        'label' => __('Module')],
                ['value' => count($branches),                                                    'label' => __('Branchenprofile')],
                ['value' => array_sum(array_map('count', array_column($integrations, 'items'))), 'label' => __('Anbindungen')],
                ['value' => count(\App\Support\Locales::codes()),                                'label' => __('Sprachen')],
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
                <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Seitenbereiche') }}">
                    @foreach ($sections as $anchor => $label)
                        <a href="#{{ $anchor }}" class="btn btn-ghost btn-sm font-medium">{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                        <x-icon name="dark_mode" class="text-base leading-none" data-theme-label />
                    </button>
                    <x-locale-switcher />
                    <x-icon-btn icon="login" tone="primary" size="sm" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                </div>
            </div>
        </header>

        {{-- Gast-Wrapperbreite wie im App-Layout: max-w-screen-2xl + px-Staffel. --}}
        <main class="mx-auto w-full max-w-screen-2xl px-2 pb-20 pt-8 sm:px-4 xl:px-8 2xl:px-12">
            {{-- Hero; isolate hält die -z-10-Hintergründe innerhalb der Karte. --}}
            <section class="relative isolate overflow-hidden rounded-box border border-base-300 bg-base-100 px-6 py-16 text-center shadow-xs sm:px-12 sm:py-20">
                <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true"
                     style="background:
                        radial-gradient(42rem 24rem at 50% -12%, color-mix(in oklab, var(--color-primary) 24%, transparent), transparent 70%),
                        radial-gradient(30rem 20rem at 6% 112%, color-mix(in oklab, var(--color-accent) 14%, transparent), transparent 70%),
                        radial-gradient(32rem 20rem at 94% 116%, color-mix(in oklab, var(--color-secondary) 18%, transparent), transparent 70%);">
                </div>
                <div class="pointer-events-none absolute inset-0 -z-10 opacity-60" aria-hidden="true"
                     style="background-image: radial-gradient(color-mix(in oklab, var(--color-base-content) 16%, transparent) 1px, transparent 1px);
                            background-size: 22px 22px;
                            mask-image: radial-gradient(ellipse at center, black 25%, transparent 72%);">
                </div>

                <img src="{{ asset('img/logo/workdiary-logo-768.png') }}" alt="WorkDiary"
                     class="mx-auto h-20 w-auto max-w-xs object-contain">

                <div class="mt-8 inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-primary">
                    <x-icon name="auto_awesome" size="1rem" />
                    {{ __('Modular · Mehrsprachig · Offline-fähig') }}
                </div>

                <h1 class="mx-auto mt-5 max-w-3xl font-['Space_Grotesk'] text-4xl font-bold tracking-tight text-base-content md:text-5xl">
                    {{ __('Das Auftragsbuch fürs ganze Tagesgeschäft') }}
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg text-base-content/75">
                    {{ __('Von der Zeiterfassung im Einsatz über Rechnung und Buchhaltung bis zu Lager, Schulung und Compliance – ein Werkzeug für den ganzen Betrieb.') }}
                </p>

                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <x-icon-btn icon="login" tone="primary" size="md" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                    @if ($registrationEnabled)
                        <x-icon-btn icon="app_registration" tone="secondary" size="md" :href="route('register')" show-label>{{ __('Organisation registrieren') }}</x-icon-btn>
                    @endif
                    <x-icon-btn icon="arrow_downward" tone="outline" size="md" href="#funktionen" show-label>{{ __('Funktionen ansehen') }}</x-icon-btn>
                </div>

                {{-- dt vor dd für Screenreader, optisch steht die Zahl oben. --}}
                <dl class="mx-auto mt-12 grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($stats as $stat)
                        <div class="flex flex-col-reverse gap-1 rounded-box border border-base-300 bg-base-100/80 px-4 py-4 backdrop-blur-sm">
                            <dt class="text-xs font-medium uppercase tracking-wider text-base-content/60">{{ $stat['label'] }}</dt>
                            <dd class="font-['Space_Grotesk'] text-3xl font-bold text-primary">{{ $stat['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Feature-Übersicht --}}
            <section id="funktionen" class="mt-20 scroll-mt-24">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Funktionen') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Alles, was der Betrieb braucht') }}</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-base text-base-content/70">{{ __('Vom ersten Eintrag im Feld bis zur fertigen Rechnung – durchgängig in einer Oberfläche.') }}</p>
                </div>

                <div class="mt-10 space-y-12">
                    @foreach ($featureGroups as $group)
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="size-2.5 shrink-0 rounded-full {{ $group['dot'] }}"></span>
                                <h3 class="font-['Space_Grotesk'] text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ $group['title'] }}</h3>
                                <span class="h-px flex-1 bg-base-300"></span>
                            </div>

                            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                @foreach ($group['items'] as $feature)
                                    <article class="group rounded-box border border-base-300 bg-base-100 p-6 shadow-xs transition motion-safe:hover:-translate-y-0.5 hover:shadow-md {{ $group['hover'] }}">
                                        <div class="flex size-12 items-center justify-center rounded-box transition {{ $group['tile'] }}">
                                            <x-icon :name="$feature['icon']" size="1.6rem" />
                                        </div>
                                        <h4 class="mt-4 font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ $feature['title'] }}</h4>
                                        <p class="mt-2 text-sm text-base-content/70">{{ $feature['text'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Branchenprofile --}}
            <section id="branchen" class="mt-20 scroll-mt-24 rounded-box border border-base-300 bg-base-100 p-8 shadow-xs sm:p-10">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Branchen') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Vorkonfiguriert für Ihre Branche') }}</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-base text-base-content/70">{{ __('Branchenprofile bringen Auftragsarten, Tätigkeiten, Anforderungen und Abläufe mit – als Startpunkt, den Sie frei anpassen.') }}</p>
                </div>

                <ul class="mx-auto mt-9 flex max-w-5xl flex-wrap justify-center gap-2.5">
                    @foreach ($branches as $branch)
                        <li class="flex items-center gap-2 rounded-full border border-base-300 bg-base-200/60 py-1.5 pl-1.5 pr-4 text-sm font-medium text-base-content">
                            <span class="flex size-7 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <x-icon :name="$branch['icon']" size="1.05rem" />
                            </span>
                            {{ $branch['label'] }}
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Integrationen --}}
            <section id="integrationen" class="mt-20 scroll-mt-24">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Integrationen') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Spricht mit Ihren Systemen') }}</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-base text-base-content/70">{{ __('Buchhaltung, Cloud-Speicher, Aufgaben- und Ticketsysteme anbinden – Import-Drehscheibe, REST-API und Webhooks inklusive.') }}</p>
                </div>

                <div class="mt-9 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($integrations as $group)
                        <div class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary">
                                    <x-icon :name="$group['icon']" size="1.2rem" />
                                </div>
                                <h3 class="font-['Space_Grotesk'] text-base font-semibold text-base-content">{{ $group['title'] }}</h3>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($group['items'] as $item)
                                    <span class="badge badge-ghost border border-base-300">{{ $item }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10 text-center">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/60">{{ __('Formate & Standards') }}</h3>
                    <div class="mx-auto mt-3 flex max-w-4xl flex-wrap justify-center gap-2">
                        @foreach ($standards as $standard)
                            <span class="badge badge-outline badge-primary">{{ $standard }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Plattform-Eigenschaften --}}
            <section id="plattform" class="mt-20 scroll-mt-24 rounded-box border border-base-300 bg-base-100 p-8 shadow-xs sm:p-10">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Plattform') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Sicher, nachvollziehbar, einsatzbereit') }}</h2>
                </div>

                <div class="mt-9 grid gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
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

            {{-- So arbeiten Sie damit --}}
            <section class="mt-20">
                <div class="text-center">
                    <div class="badge badge-ghost badge-sm uppercase tracking-[0.24em]">{{ __('Workflow') }}</div>
                    <h2 class="mt-3 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('So arbeiten Sie damit') }}</h2>
                </div>

                <ol class="mt-14 grid gap-x-6 gap-y-12 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($steps as $index => $step)
                        <li class="relative flex flex-col items-center rounded-box border border-base-300 bg-base-100 px-6 pb-6 pt-10 text-center shadow-xs">
                            <span class="absolute -top-5 flex size-10 items-center justify-center rounded-full bg-primary font-['Space_Grotesk'] text-base font-bold text-primary-content ring-4 ring-base-200">{{ $index + 1 }}</span>
                            <x-icon :name="$step['icon']" size="1.6rem" class="text-primary" />
                            <h3 class="mt-2 font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm text-base-content/70">{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Abschluss-Aufruf --}}
            <section class="relative isolate mt-20 overflow-hidden rounded-box border border-primary/30 bg-base-100 px-6 py-14 text-center shadow-xs sm:px-12">
                <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true"
                     style="background: linear-gradient(120deg,
                        color-mix(in oklab, var(--color-primary) 18%, transparent) 0%,
                        transparent 50%,
                        color-mix(in oklab, var(--color-secondary) 16%, transparent) 100%);">
                </div>
                <h2 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Bereit, loszulegen?') }}</h2>
                <p class="mx-auto mt-3 max-w-2xl text-base text-base-content/75">{{ __('Module und Branchenprofil stellen Sie passend zu Ihrem Betrieb zusammen – und erweitern sie, wenn er wächst.') }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <x-icon-btn icon="login" tone="primary" size="md" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
                    @if ($registrationEnabled)
                        <x-icon-btn icon="app_registration" tone="secondary" size="md" :href="route('register')" show-label>{{ __('Organisation registrieren') }}</x-icon-btn>
                    @endif
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
                    <a href="{{ route('legal.accessibility') }}" class="transition hover:text-base-content">{{ __('Barrierefreiheit') }}</a>
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
