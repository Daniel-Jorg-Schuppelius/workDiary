<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dim">
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
        <title>@yield('title', config('app.name', 'WorkDiary'))</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|ibm-plex-sans:400,500,600" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-50..200&display=swap" rel="stylesheet">
        <style>
            :root { --sidebar-w: min(16rem, 85vw); --app-header-h: 3.5rem; --app-footer-h: 3rem; }
            @media (min-width: 1024px) { :root { --sidebar-w: 16rem; } }
            body.sidebar-collapsed { --sidebar-w: 4rem; }
            #app-sidebar { width: var(--sidebar-w); transition: width 200ms ease; }
            @media (min-width: 1024px) {
                .with-sidebar-pad { padding-left: calc(var(--sidebar-w) + 1rem) !important; transition: padding-left 200ms ease; }
            }
            @media (min-width: 1280px) {
                .with-sidebar-pad { padding-left: calc(var(--sidebar-w) + 2rem) !important; }
            }
            @media (min-width: 1536px) {
                .with-sidebar-pad { padding-left: calc(var(--sidebar-w) + 3rem) !important; }
            }
            /* 3-stufiger Header: <900 zentriert gestapelt; 900-1695 zwei Zeilen; >=1696 eine Zeile */
            .header-row {
                display: grid;
                gap: 0.5rem 0.75rem;
                grid-template-columns: 1fr;
                grid-template-areas: "left" "right" "center";
                justify-items: center;
                align-items: center;
            }
            .header-row .header-left   { grid-area: left;   min-width: 0; max-width: 100%; }
            .header-row .header-center { grid-area: center; min-width: 0; max-width: 100%; display: flex; justify-content: center; }
            .header-row .header-right  { grid-area: right;  min-width: 0; max-width: 100%; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 0.5rem; }
            @media (min-width: 900px) {
                .header-row {
                    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                    grid-template-areas: "left right" "center center";
                    justify-items: stretch;
                }
                .header-row .header-left   { justify-self: start; }
                .header-row .header-right  { justify-self: end; flex-wrap: nowrap; justify-content: flex-end; }
                .header-row .header-center { justify-self: center; }
            }
            @media (min-width: 1696px) {
                .header-row {
                    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                    grid-template-areas: "left center right";
                }
            }
            body.sidebar-collapsed #app-sidebar [data-sidebar-label],
            body.sidebar-collapsed #app-sidebar [data-sidebar-section] {
                display: none;
            }
            body.sidebar-collapsed #app-sidebar a.menu-link,
            body.sidebar-collapsed #app-sidebar .sidebar-cta,
            body.sidebar-collapsed #app-sidebar #app-sidebar-collapse {
                gap: 0;
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
            body.sidebar-collapsed #app-sidebar .menu { padding-left: 0; padding-right: 0; }
            body.sidebar-collapsed #app-sidebar .menu li { width: 100%; }
            body.sidebar-collapsed #app-sidebar .sidebar-cta-text { display: none; }
            #app-sidebar .material-symbols-outlined { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
            /* Sidebar nutzt `base-100` als Surface — bleibt damit weiß im hellen
               Corporate-Theme und im dim-Theme die übliche Card-Fläche. Abgrenzung
               zum Body kommt über Border + Schatten. */
            #app-sidebar { background-color: var(--color-base-100); }
            #app-sidebar [data-sidebar-section] { color: color-mix(in oklab, var(--color-base-content) 55%, transparent); }

            /* Menü-Items: Farben kommen ausschließlich aus den DaisyUI-Theme-Tokens.
               Der Active-State nutzt einen neutralen, base-content-getragenen Hintergrund
               (gut lesbar in jedem Theme) und einen schmalen `--color-primary`-Akzentbalken
               links — so bleibt die Theme-Akzentfarbe präsent, ohne den ganzen Eintrag
               einzufärben. Hover ist eine noch dezentere base-content-Tönung. */
            #app-sidebar .menu :where(li) > a,
            #app-sidebar .menu :where(li) > .menu-link {
                color: var(--color-base-content);
                border-radius: var(--radius-field, 0.5rem);
                transition: background-color .15s ease, color .15s ease;
            }
            #app-sidebar .menu :where(li) > a:hover,
            #app-sidebar .menu :where(li) > .menu-link:hover,
            #app-sidebar .menu :where(li) > a:focus-visible,
            #app-sidebar .menu :where(li) > .menu-link:focus-visible {
                background-color: color-mix(in oklab, var(--color-base-content) 8%, transparent);
                color: var(--color-base-content);
            }
            #app-sidebar .menu :where(li) > .menu-active,
            #app-sidebar .menu :where(li) > .menu-active:hover,
            #app-sidebar .menu :where(li) > .menu-active:focus,
            #app-sidebar .menu :where(li) > a[aria-current="page"] {
                background-color: color-mix(in oklab, var(--color-base-content) 12%, transparent) !important;
                color: var(--color-base-content) !important;
                font-weight: 600;
                box-shadow: inset 3px 0 0 var(--color-primary);
            }
            /* Icon im aktiven Eintrag in der Theme-Akzentfarbe — zieht den Blick, ohne den
               kompletten Eintrag mit Primary zu überlagern. */
            #app-sidebar .menu :where(li) > .menu-active .material-symbols-outlined,
            #app-sidebar .menu :where(li) > a[aria-current="page"] .material-symbols-outlined {
                color: var(--color-primary);
                font-variation-settings: 'FILL' 1, 'wght' 500;
            }
            /* Sidebar-CTA „Neuer Eintrag“ als primäre Action erhält den vollen Theme-Primary. */
            #app-sidebar .sidebar-cta { color: var(--color-primary-content); }

            /* Collapsible Section-Header (<details>): optisch wie eine Section-Überschrift,
               aber klickbar mit drehendem Chevron. Im aktiven Zustand (offen oder mit
               aktivem Kind-Item) wird die Schrift kräftiger. */
            #app-sidebar details.sidebar-section-collapsible { width: 100%; }
            #app-sidebar details.sidebar-section-collapsible > summary {
                list-style: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: .625rem;
                padding: .375rem .5rem;
                border-radius: var(--radius-field, .5rem);
                font-size: 0.65rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.18em;
                color: color-mix(in oklab, var(--color-base-content) 55%, transparent);
                transition: background-color .15s ease, color .15s ease;
                user-select: none;
            }
            #app-sidebar details.sidebar-section-collapsible > summary::-webkit-details-marker { display: none; }
            #app-sidebar details.sidebar-section-collapsible > summary::marker { content: ''; }
            #app-sidebar details.sidebar-section-collapsible > summary:hover {
                background-color: color-mix(in oklab, var(--color-base-content) 6%, transparent);
                color: var(--color-base-content);
            }
            #app-sidebar details.sidebar-section-collapsible[open] > summary {
                color: color-mix(in oklab, var(--color-base-content) 75%, transparent);
            }
            #app-sidebar .sidebar-section-icon { font-size: 1rem; opacity: .85; }
            #app-sidebar .sidebar-section-chevron {
                margin-left: auto;
                font-size: 1.1rem;
                opacity: .6;
                transition: transform .15s ease;
            }
            #app-sidebar details.sidebar-section-collapsible[open] > summary .sidebar-section-chevron {
                transform: rotate(180deg);
            }

            /* Collapsed-Mode: Section-Titel + Summary-Text/Chevron verschwinden, Details
               werden zwangsweise geöffnet, damit alle Item-Icons als flache Liste sichtbar
               bleiben. Nur die Icons der Items zählen dann. */
            body.sidebar-collapsed #app-sidebar .sidebar-section-summary { display: none; }
            body.sidebar-collapsed #app-sidebar details.sidebar-section-collapsible > ul { display: block !important; }
            body.sidebar-collapsed #app-sidebar .sidebar-section-chevron,
            body.sidebar-collapsed #app-sidebar .sidebar-section-icon { display: none; }
            /* Material Symbols Outlined ist eine Single-Color-Variable-Font und folgt automatisch
               currentColor — sie funktioniert in jedem DaisyUI-Theme, auf jedem Hintergrund und in
               allen Button-/Badge-/Alert-Farben ohne weitere CSS-Hacks. */
            .material-symbols-outlined { font-size: 1.25rem; line-height: 1; vertical-align: middle; }

            /* Backwards-Compat: bestehende `material-icons-two-tone`-Spans
               werden transparent über die Symbols-Outlined-Variable-Font gerendert, bis sie
               schrittweise auf die x-icon-Komponente migriert sind. Damit funktionieren alle
               bisherigen Pages in jedem DaisyUI-Theme korrekt — ohne den separaten
               Two-Tone-Color-Font und ohne die bisher nötigen `brightness(0) invert(1)`-Hacks
               für farbige Buttons/Badges/Alerts. */
            .material-icons-two-tone {
                font-family: 'Material Symbols Outlined';
                font-weight: normal;
                font-style: normal;
                font-size: 1.25rem;
                line-height: 1;
                letter-spacing: normal;
                text-transform: none;
                display: inline-block;
                white-space: nowrap;
                word-wrap: normal;
                direction: ltr;
                vertical-align: middle;
                -webkit-font-feature-settings: 'liga';
                -webkit-font-smoothing: antialiased;
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            }
        </style>
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
    @php
        $_bodyMode = (session('work_mode', 'legacy') === 'legacy' && filled(config('database.connections.legacy.database'))) ? 'legacy' : 'new';
    @endphp
    <body class="min-h-screen text-base-content {{ $_bodyMode === 'legacy' ? 'bg-base-200' : 'bg-linear-to-b from-base-200 to-base-300' }}" data-mode="{{ $_bodyMode }}">
        @php
            $currentMode = session('work_mode', 'legacy');
            $legacyConfigured = filled(config('database.connections.legacy.database'));
            $effectiveMode = $currentMode === 'legacy' && $legacyConfigured ? 'legacy' : 'new';
            $indexRoute = $effectiveMode === 'legacy' ? 'legacy.diary.index' : 'duties.index';
            $createRoute = $effectiveMode === 'legacy' ? 'legacy.diary.create' : 'diary.create';
            $originRoute = request()->route()?->getName() ?? 'home';
            $isLegacyMode = $effectiveMode === 'legacy';
            $legacyUserId = \App\Legacy\Support\LegacyRoleResolver::resolveLegacyUserId(Auth::user());
            $isLegacyAdmin = \App\Legacy\Support\LegacyRoleResolver::isAdmin(Auth::user());
            $currentLocale = app()->getLocale();
            $supportedLocales = [
                'de' => ['label' => __('Deutsch'),  'code' => 'DE'],
                'en' => ['label' => __('Englisch'), 'code' => 'EN'],
            ];
        @endphp

        <header id="app-header" class="sticky top-0 z-50 bg-base-100 border-b border-base-300 shadow-xs">
            @php
                $_hasCenter = \Illuminate\Support\Facades\View::hasSection('nav-center');
                $_showCenteredRange = ! $_hasCenter && auth()->check() && ! $isLegacyMode;
                $_useGrid = $_hasCenter || $_showCenteredRange;
            @endphp
            <div class="header-row w-full px-4 xl:px-8 2xl:px-12 min-h-14 py-2">
                <div class="header-left flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 group min-w-0">
                        <span class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary transition group-hover:opacity-80 shrink-0">WorkDiary</span>
                        <span class="text-base-content/40">/</span>
                        <span class="font-['Space_Grotesk'] font-semibold text-base-content truncate">@yield('nav-title', __('Tagebuch'))</span>
                    </a>
                </div>
                @if ($_useGrid)
                    <div class="header-center">
                        @hasSection('nav-center')
                            @yield('nav-center')
                        @else
                            <x-header-date-range align="center" />
                        @endif
                    </div>
                @endif
                <div class="header-right">
                    @auth
                        @php
                            // Hauptnavigation: pro Modus eine Liste mit Routenname + Label.
                            $mainNavItems = $isLegacyMode
                                ? [
                                    ['route' => 'legacy.diary.week',           'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week', 'modal' => false, 'matches' => ['legacy.diary.week']],
                                    ['route' => $indexRoute,                   'label' => __('Arbeitsliste'),  'icon' => 'list_alt',           'modal' => false, 'matches' => [$indexRoute, 'legacy.oncall.*', 'legacy.notdienst.*']],
                                    ['route' => 'legacy.archive.index',        'label' => __('Archiv'),        'icon' => 'inventory_2',        'modal' => false, 'matches' => ['legacy.archive.*']],
                                    ['route' => 'legacy.callcenter.notdienst', 'label' => __('Zentrale'),      'icon' => 'support_agent',      'modal' => false, 'matches' => ['legacy.callcenter.*', 'legacy.overview.*']],
                                ]
                                : [
                                    ['route' => $indexRoute,                'label' => __('Arbeitsliste'),   'icon' => 'list_alt',         'modal' => false, 'matches' => [$indexRoute, 'diary.*']],
                                    ['route' => 'week.index',               'label' => __('Wochenansicht'),  'icon' => 'calendar_view_week','modal' => false, 'matches' => ['week.index']],
                                    ['route' => 'kanban.index',             'label' => __('Kanban'),         'icon' => 'view_kanban',      'modal' => false, 'matches' => ['kanban.index']],
                                    ['route' => 'duty-plans.index',         'label' => __('Dienstpläne'),    'icon' => 'event_available',  'modal' => false, 'matches' => ['duty-plans.*']],
                                    ['route' => 'schedule.index',           'label' => __('Schichtplan'),    'icon' => 'schedule',         'modal' => false, 'matches' => ['schedule.*']],
                                    ['route' => 'timesheets.index',         'label' => __('Stundenzettel'),  'icon' => 'description',      'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                                    ['route' => 'customers.index',          'label' => __('Kunden'),         'icon' => 'badge',            'modal' => false, 'matches' => ['customers.*']],
                                    ['route' => 'projects.index',           'label' => __('Projekte'),       'icon' => 'folder_special',   'modal' => false, 'matches' => ['projects.*']],
                                    ['route' => 'invoices.index',           'label' => __('Rechnungen'),     'icon' => 'receipt_long',     'modal' => false, 'matches' => ['invoices.*']],
                                    ['route' => 'flex.index',               'label' => __('Gleitzeit'),      'icon' => 'hourglass_top',    'modal' => false, 'matches' => ['flex.*']],
                                    ['route' => 'archive.index',            'label' => __('Archiv'),         'icon' => 'inventory_2',      'modal' => false, 'matches' => ['archive.*']],
                                ];

                            $manageNavItems = [];
                            $adminNavItems  = [];
                            if ($isLegacyAdmin) {
                                $manageNavItems[] = ['route' => 'legacy.users.index', 'label' => __('Mitarbeiter'), 'icon' => 'group',           'modal' => false];
                                $manageNavItems[] = ['route' => 'holidays.index',     'label' => __('Feiertage'),   'icon' => 'celebration',     'modal' => false];
                                if (! $isLegacyMode) {
                                    $manageNavItems[] = ['route' => 'qualifications.index',         'label' => __('Qualifikationen'),  'icon' => 'workspace_premium','modal' => false];
                                    $manageNavItems[] = ['route' => 'shift-types.index',             'label' => __('Schichttypen'),     'icon' => 'work_history',     'modal' => false];
                                    $manageNavItems[] = ['route' => 'materials.index',               'label' => __('Materialien'),      'icon' => 'inventory',        'modal' => false];
                                    $manageNavItems[] = ['route' => 'tags.index',                    'label' => __('Tags'),             'icon' => 'label',            'modal' => false];
                                    $manageNavItems[] = ['route' => 'flex.admin',                    'label' => __('Gleitzeit Team'),   'icon' => 'groups',           'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.organizations.index',     'label' => __('Organisationen'),   'icon' => 'corporate_fare',   'modal' => false];
                                }
                                $adminNavItems[] = ['route' => 'audit.index',                       'label' => __('Audit-Log'),        'icon' => 'fact_check',       'modal' => false];
                                $adminNavItems[] = ['route' => 'admin.plugins.index',                'label' => __('Plugins'),          'icon' => 'extension',        'modal' => false];
                                $adminNavItems[] = ['route' => 'admin.legacy-migration.index',      'label' => __('Legacy-Migration'), 'icon' => 'sync_alt',         'modal' => false];
                            }
                            if (! $isLegacyMode && \Illuminate\Support\Facades\Gate::allows('manage-members')) {
                                $manageNavItems[] = ['route' => 'org.members.index', 'label' => __('Mitglieder'), 'icon' => 'badge', 'modal' => false];
                            }

                            $userNavItems = [];
                            if (! $isLegacyMode) {
                                $userNavItems[] = ['route' => 'account.profile.edit',  'label' => __('Profil bearbeiten'), 'modal' => true];
                                $userNavItems[] = ['route' => 'account.password.edit', 'label' => __('Passwort ändern'),  'modal' => true];
                                $userNavItems[] = ['route' => 'account.work-schedule', 'label' => __('Arbeitszeit-Modell'), 'modal' => false];
                            } else {
                                $userNavItems[] = ['route' => 'legacy.account.password.edit', 'label' => __('Passwort ändern'), 'modal' => true];
                            }
                            $userNavItems[] = ['route' => 'profile.api-tokens.index', 'label' => __('API-Tokens'), 'modal' => false];

                            $isAdminActive  = collect($adminNavItems)->contains(fn ($i) => request()->routeIs($i['route']));
                            $isManageActive = collect($manageNavItems)->contains(fn ($i) => request()->routeIs($i['route']));
                            $isUserActive = collect($userNavItems)->contains(fn ($i) => request()->routeIs($i['route']));

                            // ---------------------------------------------------------------
                            // Sidebar-Sektionen (nur im modernen Modus). Thematische Gruppen
                            // statt einer langen Flachliste; Verwaltung + System sind
                            // collapsible, weil sie deutlich mehr Einträge haben (und das
                            // Archiv soll perspektivisch eigene Submenüs bekommen).
                            // ---------------------------------------------------------------
                            $sidebarSections = [];
                            if (! $isLegacyMode) {
                                $sidebarSections[] = [
                                    'key'         => 'work',
                                    'label'       => __('Tagesgeschäft'),
                                    'collapsible' => false,
                                    'items'       => [
                                        ['route' => $indexRoute,    'label' => __('Arbeitsliste'),  'icon' => 'list_alt',          'modal' => false, 'matches' => [$indexRoute, 'diary.*']],
                                        ['route' => 'week.index',   'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week','modal' => false, 'matches' => ['week.index']],
                                        ['route' => 'kanban.index', 'label' => __('Kanban'),        'icon' => 'view_kanban',       'modal' => false, 'matches' => ['kanban.index']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'plan',
                                    'label'       => __('Planung'),
                                    'collapsible' => false,
                                    'items'       => [
                                        ['route' => 'duty-plans.index', 'label' => __('Dienstpläne'),   'icon' => 'event_available', 'modal' => false, 'matches' => ['duty-plans.*']],
                                        ['route' => 'schedule.index',   'label' => __('Schichtplan'),   'icon' => 'schedule',        'modal' => false, 'matches' => ['schedule.*']],
                                        ['route' => 'timesheets.index', 'label' => __('Stundenzettel'), 'icon' => 'description',     'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                                        ['route' => 'flex.index',       'label' => __('Gleitzeit'),     'icon' => 'hourglass_top',   'modal' => false, 'matches' => ['flex.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'data',
                                    'label'       => __('Stammdaten'),
                                    'collapsible' => false,
                                    'items'       => [
                                        ['route' => 'customers.index', 'label' => __('Kunden'),   'icon' => 'badge',          'modal' => false, 'matches' => ['customers.*']],
                                        ['route' => 'projects.index',  'label' => __('Projekte'), 'icon' => 'folder_special', 'modal' => false, 'matches' => ['projects.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'reports',
                                    'label'       => __('Auswertungen'),
                                    'collapsible' => false,
                                    'items'       => [
                                        ['route' => 'reports.my-month',         'label' => __('Mein Monat'),         'icon' => 'calendar_view_week',  'modal' => false, 'matches' => ['reports.my-month']],
                                        ['route' => 'reports.my-year',          'label' => __('Mein Jahr'),          'icon' => 'calendar_view_month', 'modal' => false, 'matches' => ['reports.my-year']],
                                        ['route' => 'reports.week-by-user',     'label' => __('Woche pro Mitarbeiter'), 'icon' => 'date_range',       'modal' => false, 'matches' => ['reports.week-by-user']],
                                        ['route' => 'reports.customer-project', 'label' => __('Kunden & Projekte'), 'icon' => 'pie_chart',           'modal' => false, 'matches' => ['reports.customer-project']],
                                        ['route' => 'reports.project-details',  'label' => __('Projekt-Details'),    'icon' => 'analytics',           'modal' => false, 'matches' => ['reports.project-details']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'archive',
                                    'label'       => __('Archiv'),
                                    'collapsible' => false,
                                    'items'       => [
                                        ['route' => 'archive.index', 'label' => __('Archiv-Übersicht'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['archive.*']],
                                    ],
                                ];
                            }
                        @endphp

                        @if ($isLegacyMode)
                            {{-- Legacy-Modus: klassische Inline-/Dropdown-Navigation im Header --}}
                            <nav class="hidden xl:flex items-center gap-1">
                                @foreach ($mainNavItems as $item)
                                    @php $active = collect($item['matches'])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                    <a href="{{ route($item['route']) }}"
                                       @if ($item['modal']) data-entry-modal-trigger @endif
                                       class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-ghost' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </nav>

                            <div class="dropdown dropdown-end xl:hidden">
                                <label tabindex="0" class="btn btn-sm btn-ghost">☰ {{ __('Navigation') }}</label>
                                <ul tabindex="0" class="dropdown-content menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                    @foreach ($mainNavItems as $item)
                                        @php $active = collect($item['matches'])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                        <li>
                                            <a href="{{ route($item['route']) }}"
                                               @if ($item['modal']) data-entry-modal-trigger @endif
                                               class="{{ $active ? 'active' : '' }}">
                                                {{ $item['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            @if (! empty($manageNavItems))
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-sm {{ $isManageActive ? 'btn-primary' : 'btn-ghost' }}" title="{{ __('Verwaltung') }}">
                                        ≡ <span class="hidden sm:inline ml-1">{{ __('Verwaltung') }}</span>
                                    </label>
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        @foreach ($manageNavItems as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route']) }}" class="{{ $active ? 'active' : '' }}">
                                                    {{ $item['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($adminNavItems))
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-sm {{ $isAdminActive ? 'btn-primary' : 'btn-ghost' }}" title="{{ __('Administration') }}">
                                        ⚙ <span class="hidden sm:inline ml-1">{{ __('Admin') }}</span>
                                    </label>
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        @foreach ($adminNavItems as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route']) }}" class="{{ $active ? 'active' : '' }}">
                                                    {{ $item['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <a href="{{ route($createRoute) }}" data-entry-modal-trigger class="btn btn-sm btn-primary">+ {{ __('Neuer Eintrag') }}</a>
                        @else
                            {{-- Sidebar-Toggle (nur < lg) --}}
                            <button type="button"
                                    id="app-sidebar-toggle"
                                    class="btn btn-sm btn-ghost btn-square lg:hidden"
                                    aria-label="{{ __('Navigation öffnen') }}"
                                    aria-controls="app-sidebar"
                                    aria-expanded="false">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>

                            @if (! empty($manageNavItems))
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-sm {{ $isManageActive ? 'btn-primary' : 'btn-ghost' }} gap-1" title="{{ __('Verwaltung') }}">
                                        <x-icon name="manage_accounts" class="text-[1.1rem]" />
                                        <span class="hidden sm:inline">{{ __('Verwaltung') }}</span>
                                    </label>
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-[min(15rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        @foreach ($manageNavItems as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                    <x-icon :name="$item['icon'] ?? 'tune'" class="text-[1.1rem]" />
                                                    <span class="truncate">{{ $item['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($adminNavItems))
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-sm {{ $isAdminActive ? 'btn-primary' : 'btn-ghost' }} gap-1" title="{{ __('System') }}">
                                        <x-icon name="settings" class="text-[1.1rem]" />
                                        <span class="hidden sm:inline">{{ __('System') }}</span>
                                    </label>
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-[min(15rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        @foreach ($adminNavItems as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                    <x-icon :name="$item['icon'] ?? 'tune'" class="text-[1.1rem]" />
                                                    <span class="truncate">{{ $item['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif

                        @isset($stopwatchEntry)
                            @if ($stopwatchEntry)
                                <div class="flex items-center gap-1.5 rounded-box border border-primary/40 bg-primary/10 px-2 py-1 shadow-xs"
                                     title="{{ $stopwatchEntry->description ?: __('Läuft…') }}"
                                     x-data="{ s: 0 }"
                                     x-init="s = Math.max(0, Math.floor((Date.now() - new Date('{{ $stopwatchEntry->started_at?->toIso8601String() }}').getTime())/1000)); setInterval(() => s++, 1000);">
                                    <span class="relative flex size-2">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                                        <span class="relative inline-flex size-2 rounded-full bg-primary"></span>
                                    </span>
                                    <span class="font-['Space_Grotesk'] text-sm font-semibold tabular-nums text-primary"
                                          x-text="String(Math.floor(s/3600)).padStart(2,'0') + ':' + String(Math.floor((s%3600)/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0')">00:00:00</span>
                                    <form method="POST" action="{{ route('stopwatch.stop') }}" class="leading-none">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-ghost btn-square text-error" title="{{ __('Stoppen') }}" aria-label="{{ __('Stoppen') }}">
                                            <x-icon name="stop_circle" filled />
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @endisset

                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                            <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                                <span data-theme-label class="text-base leading-none">◐</span>
                            </button>
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-sm btn-ghost btn-square" title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0 0c2.5 0 4-4.03 4-9s-1.5-9-4-9m0 18c-2.5 0-4-4.03-4-9s1.5-9 4-9M3 12h18" />
                                    </svg>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu z-50 w-[min(10rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-1 shadow">
                                    @foreach ($supportedLocales as $code => $locale)
                                        <li>
                                            <form method="POST" action="{{ route('locale.switch', $code) }}">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2 {{ $currentLocale === $code ? 'active' : '' }}">
                                                    <span class="rounded px-1 py-0.5 font-mono text-[0.65rem] font-bold leading-none ring-1 ring-current opacity-70">{{ $locale['code'] }}</span>
                                                    <span>{{ $locale['label'] }}</span>
                                                    @if ($currentLocale === $code)
                                                        <span class="ml-auto opacity-60">•</span>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            @if ($legacyConfigured)
                                {{-- Legacy-Toggle-Switch --}}
                                <form method="POST"
                                      action="{{ route('mode.switch', $isLegacyMode ? 'new' : 'legacy') }}"
                                      id="mode-switch-form"
                                      class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="hidden" name="origin" value="{{ $originRoute }}">
                                    <label for="mode-switch-toggle"
                                           class="text-[0.65rem] font-semibold uppercase tracking-widest cursor-pointer select-none
                                                  {{ $isLegacyMode ? 'text-base-content/70' : 'text-base-content/40' }}"
                                           title="{{ __('Modus wechseln') }}">
                                        Legacy
                                    </label>
                                    <button type="submit" id="mode-switch-toggle" role="switch"
                                            aria-checked="{{ $isLegacyMode ? 'true' : 'false' }}"
                                            aria-label="{{ __('Legacy-Modus') }}"
                                            title="{{ __('Modus wechseln') }}"
                                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full
                                                   border-2 border-transparent transition-colors duration-200 focus:outline-none focus-visible:ring-2
                                                   focus-visible:ring-primary focus-visible:ring-offset-2
                                                   {{ $isLegacyMode ? 'bg-primary' : 'bg-base-300' }}">
                                        <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow-md ring-0
                                                     transform transition-transform duration-200
                                                     {{ $isLegacyMode ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </form>
                            @endif
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-sm {{ $isUserActive ? 'btn-primary' : 'btn-ghost' }}" title="{{ Auth::user()->name }}">
                                    ⎋ <span class="ml-1 max-w-32 truncate">{{ Auth::user()->name }}</span>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                    @foreach ($userNavItems as $item)
                                        @php $active = request()->routeIs($item['route']); @endphp
                                        <li>
                                            <a href="{{ route($item['route']) }}"
                                               @if ($item['modal']) data-entry-modal-trigger @endif
                                               class="{{ $active ? 'active' : '' }}">
                                                {{ $item['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                                            @csrf
                                            <button type="submit" class="flex w-full items-center gap-2 text-error">
                                                ⎋ <span>{{ __('Abmelden') }}</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                            <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                                <span data-theme-label class="text-base leading-none">◐</span>
                            </button>
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-sm btn-ghost btn-square" title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0 0c2.5 0 4-4.03 4-9s-1.5-9-4-9m0 18c-2.5 0-4-4.03-4-9s1.5-9 4-9M3 12h18" />
                                    </svg>
                                </label>
                                <ul tabindex="0" class="dropdown-content menu z-50 w-[min(10rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-1 shadow">
                                    @foreach ($supportedLocales as $code => $locale)
                                        <li>
                                            <form method="POST" action="{{ route('locale.switch', $code) }}">
                                                @csrf
                                                <button type="submit" class="flex w-full items-center gap-2 {{ $currentLocale === $code ? 'active' : '' }}">
                                                    <span class="rounded px-1 py-0.5 font-mono text-[0.65rem] font-bold leading-none ring-1 ring-current opacity-70">{{ $locale['code'] }}</span>
                                                    <span>{{ $locale['label'] }}</span>
                                                    @if ($currentLocale === $code)
                                                        <span class="ml-auto opacity-60">•</span>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <a href="{{ route('login') }}" class="btn btn-sm btn-primary">⇢ {{ __('Anmelden') }}</a>
                        </div>
                    @endauth
                </div>
            </div>
            @auth
                @unless ($isLegacyMode)
                    {{-- Datumsauswahl wird im .header-center innerhalb der .header-row gerendert. --}}
                @endunless
            @endauth
        </header>

        @auth
        @unless ($isLegacyMode)
        {{-- Sidebar: persistent ab lg, sonst Drawer --}}
        <aside id="app-sidebar"
               class="fixed left-0 z-40 -translate-x-full transform border-r border-base-300 shadow-sm transition-transform duration-200 lg:translate-x-0"
               style="top: var(--app-header-h); bottom: var(--app-footer-h);"
               aria-label="{{ __('Hauptnavigation') }}"
               data-sidebar>
            <div class="flex flex-col gap-3 overflow-y-auto overflow-x-hidden px-2 py-3"
                 style="height: calc(100dvh - var(--app-header-h) - var(--app-footer-h));">
                <a href="{{ route($createRoute) }}" data-entry-modal-trigger
                   class="sidebar-cta btn btn-sm btn-primary w-full gap-2"
                   title="{{ __('Neuer Eintrag') }}">
                    <x-icon name="add_circle" />
                    <span class="sidebar-cta-text">{{ __('Neuer Eintrag') }}</span>
                </a>

                <div class="flex flex-col gap-4">
                    @foreach ($sidebarSections as $section)
                        @php
                            $sectionActive = collect($section['items'])->contains(
                                fn ($i) => collect($i['matches'] ?? [$i['route']])->contains(fn ($m) => request()->routeIs($m))
                            );
                        @endphp
                        @if (! empty($section['collapsible']))
                            <details class="sidebar-section sidebar-section-collapsible" @if ($sectionActive) open @endif>
                                <summary class="sidebar-section-summary">
                                    <x-icon :name="$section['icon'] ?? 'folder'" class="sidebar-section-icon" />
                                    <span data-sidebar-label class="flex-1 truncate">{{ $section['label'] }}</span>
                                    <x-icon name="expand_more" class="sidebar-section-chevron" />
                                </summary>
                                <ul class="menu menu-sm w-full gap-0.5 p-0 pt-1">
                                    @foreach ($section['items'] as $item)
                                        @php $active = collect($item['matches'] ?? [$item['route']])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                        <li>
                                            <a href="{{ route($item['route']) }}"
                                               @if (! empty($item['modal'])) data-entry-modal-trigger @endif
                                               class="menu-link flex items-center gap-3 {{ $active ? 'menu-active' : '' }}"
                                               title="{{ $item['label'] }}">
                                                <x-icon :name="$item['icon'] ?? 'circle'" />
                                                <span data-sidebar-label class="truncate transition-opacity duration-150">{{ $item['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <div class="sidebar-section">
                                <p data-sidebar-section class="px-2 pb-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/50 transition-opacity duration-150">{{ $section['label'] }}</p>
                                <ul class="menu menu-sm w-full gap-0.5 p-0">
                                    @foreach ($section['items'] as $item)
                                        @php $active = collect($item['matches'] ?? [$item['route']])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                        <li>
                                            <a href="{{ route($item['route']) }}"
                                               @if (! empty($item['modal'])) data-entry-modal-trigger @endif
                                               class="menu-link flex items-center gap-3 {{ $active ? 'menu-active' : '' }}"
                                               title="{{ $item['label'] }}">
                                                <x-icon :name="$item['icon'] ?? 'circle'" />
                                                <span data-sidebar-label class="truncate transition-opacity duration-150">{{ $item['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="mt-auto pt-2">
                    <button type="button"
                            id="app-sidebar-collapse"
                            class="btn btn-sm btn-ghost w-full justify-center gap-2"
                            aria-label="{{ __('Sidebar ein-/ausklappen') }}"
                            title="{{ __('Sidebar ein-/ausklappen') }}">
                        <x-icon name="chevron_left" data-sidebar-collapse-icon />
                        <span data-sidebar-label>{{ __('Einklappen') }}</span>
                    </button>
                </div>
            </div>
        </aside>
        <div id="app-sidebar-backdrop"
             class="fixed inset-x-0 z-30 hidden bg-black/40 backdrop-blur-[1px] lg:hidden"
             style="top: var(--app-header-h); bottom: var(--app-footer-h);"
             data-sidebar-backdrop></div>
        @endunless
        @endauth

        <script>
            // Header-Höhe als CSS-Var pflegen (Sidebar + Content folgen automatisch)
            (function () {
                var header = document.getElementById('app-header');
                if (!header) return;
                var apply = function () {
                    var h = header.offsetHeight;
                    if (h > 0) document.documentElement.style.setProperty('--app-header-h', h + 'px');
                };
                apply();
                if (typeof ResizeObserver === 'function') {
                    new ResizeObserver(apply).observe(header);
                } else {
                    window.addEventListener('resize', apply);
                }
                window.addEventListener('load', apply);
            })();
        </script>

        @if (session('mode_toast'))
        <div id="mode-toast"
             class="fixed bottom-24 left-1/2 z-200 -translate-x-1/2 translate-y-0 opacity-100 transition-all duration-500"
             aria-live="polite">
            <div class="flex items-center gap-3 rounded-2xl border border-base-300 bg-base-100/90 px-5 py-3 text-sm shadow-xl backdrop-blur-sm">
                <span class="text-base">{{ $effectiveMode === 'legacy' ? '🗂' : '✨' }}</span>
                <span class="font-medium">{{ session('mode_toast') }}</span>
            </div>
        </div>
        <script>
            (function () {
                var el = document.getElementById('mode-toast');
                if (!el) return;
                setTimeout(function () {
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(-50%) translateY(0.75rem)';
                    setTimeout(function () { el.remove(); }, 500);
                }, 3000);
            })();
        </script>
        @endif

        @php
            $_wrapperMaxW = (Auth::check() && ! $isLegacyMode) ? 'max-w-none' : 'max-w-screen-2xl';
        @endphp
        <div class="mx-auto flex @yield('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))]') w-full {{ $_wrapperMaxW }} flex-col px-4 pt-6 pb-20 xl:px-8 2xl:px-12 @auth @unless($isLegacyMode) with-sidebar-pad @endunless @endauth">
            @if (session('success'))
                <div class="alert alert-success mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-error mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('info'))
                <div class="alert alert-info mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('info') }}
                </div>
            @endif

            <main class="flex-1 @yield('main-class', '')">
                @yield('content')
            </main>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 h-12 bg-base-100 border-t border-base-300 shadow-xs">
            <div class="mx-auto flex w-full {{ $_wrapperMaxW }} items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                &copy; {{ date('Y') }} WorkDiary. {{ __('Alle Rechte vorbehalten.') }}
            </div>
        </footer>

        <dialog id="action-confirm-dialog" class="modal">
                <div class="modal-box">
                    <h3 id="action-confirm-title" class="text-lg font-bold">{{ __('Aktion bestätigen') }}</h3>
                    <p id="action-confirm-message" class="py-4 text-sm text-base-content/75">{{ __('Möchtest du diese Aktion wirklich ausführen?') }}</p>
                    <div class="modal-action">
                        <form method="dialog">
                            <button class="btn btn-ghost">{{ __('Abbrechen') }}</button>
                        </form>
                        <button id="action-confirm-submit" type="button" class="btn btn-error">{{ __('Ausführen') }}</button>
                    </div>
                </div>
            </dialog>

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

                    var confirmDialog = document.getElementById('action-confirm-dialog');
                    var confirmTitle = document.getElementById('action-confirm-title');
                    var confirmMessage = document.getElementById('action-confirm-message');
                    var confirmSubmit = document.getElementById('action-confirm-submit');
                    var pendingForm = null;
                    var pendingResolve = null;

                    function openConfirm(opts) {
                        opts = opts || {};
                        if (confirmTitle) {
                            confirmTitle.textContent = opts.title || '{{ __('Aktion bestätigen') }}';
                        }
                        if (confirmMessage) {
                            confirmMessage.textContent = opts.message || '{{ __('Möchtest du diese Aktion wirklich ausführen?') }}';
                        }
                        confirmSubmit.textContent = opts.label || '{{ __('Ausführen') }}';
                        if (typeof confirmDialog.showModal === 'function') {
                            confirmDialog.showModal();
                        }
                    }

                    // Programmatic API: returns Promise<boolean>
                    window.confirmAction = function (opts) {
                        if (!confirmDialog) {
                            return Promise.resolve(false);
                        }
                        return new Promise(function (resolve) {
                            pendingForm = null;
                            pendingResolve = resolve;
                            openConfirm(typeof opts === 'string' ? { message: opts } : opts);
                        });
                    };

                    if (confirmDialog && confirmSubmit) {
                        document.addEventListener('submit', function (event) {
                            var form = event.target;
                            if (!(form instanceof HTMLFormElement)) {
                                return;
                            }

                            if (!form.hasAttribute('data-confirm-dialog')) {
                                return;
                            }

                            event.preventDefault();
                            pendingForm = form;
                            pendingResolve = null;

                            openConfirm({
                                title:   form.getAttribute('data-confirm-title') || undefined,
                                message: form.getAttribute('data-confirm-message') || undefined,
                                label:   form.getAttribute('data-confirm-label') || undefined,
                            });
                        });

                        // Allow data-confirm-dialog on buttons / anchors directly.
                        document.addEventListener('click', function (event) {
                            var trigger = event.target.closest('[data-confirm-dialog]');
                            if (!trigger || trigger.tagName === 'FORM') {
                                return;
                            }
                            // If trigger is a submit button inside a data-confirm-dialog form, the
                            // form-level handler will catch it on submit. Otherwise handle here.
                            var ownerForm = trigger.form || (trigger.closest && trigger.closest('form'));
                            if (ownerForm && ownerForm.hasAttribute('data-confirm-dialog')) {
                                return;
                            }

                            event.preventDefault();
                            pendingResolve = null;

                            if (trigger.tagName === 'BUTTON' && (trigger.type === 'submit' || !trigger.type) && ownerForm) {
                                pendingForm = ownerForm;
                            } else if (trigger.tagName === 'A' && trigger.href) {
                                pendingForm = null;
                                pendingResolve = function (ok) {
                                    if (ok) window.location.href = trigger.href;
                                };
                            } else {
                                pendingForm = null;
                                pendingResolve = function (ok) {
                                    if (ok) trigger.dispatchEvent(new CustomEvent('confirmed-action', { bubbles: true }));
                                };
                            }

                            openConfirm({
                                title:   trigger.getAttribute('data-confirm-title') || undefined,
                                message: trigger.getAttribute('data-confirm-message') || undefined,
                                label:   trigger.getAttribute('data-confirm-label') || undefined,
                            });
                        });

                        confirmSubmit.addEventListener('click', function () {
                            var formToSubmit = pendingForm;
                            var resolver = pendingResolve;
                            pendingForm = null;
                            pendingResolve = null;
                            confirmDialog.close();
                            if (formToSubmit) {
                                formToSubmit.submit();
                            }
                            if (resolver) {
                                resolver(true);
                            }
                        });

                        confirmDialog.addEventListener('close', function () {
                            var resolver = pendingResolve;
                            pendingForm = null;
                            pendingResolve = null;
                            if (resolver) {
                                resolver(false);
                            }
                        });
                    }

                    // Sidebar (Drawer) Toggle für < lg
                    var sidebar = document.getElementById('app-sidebar');
                    var sidebarToggle = document.getElementById('app-sidebar-toggle');
                    var sidebarBackdrop = document.getElementById('app-sidebar-backdrop');
                    var sidebarCollapse = document.getElementById('app-sidebar-collapse');
                    var sidebarCollapseIcon = document.querySelector('[data-sidebar-collapse-icon]');

                    // Collapse-State auf lg+ aus localStorage anwenden
                    function applyCollapsed(collapsed) {
                        document.body.classList.toggle('sidebar-collapsed', collapsed);
                        if (sidebarCollapseIcon) {
                            sidebarCollapseIcon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
                        }
                    }
                    try {
                        applyCollapsed(localStorage.getItem('workDiarySidebarCollapsed') === '1');
                    } catch (e) { /* ignore */ }

                    if (sidebarCollapse) {
                        sidebarCollapse.addEventListener('click', function () {
                            var next = !document.body.classList.contains('sidebar-collapsed');
                            applyCollapsed(next);
                            try { localStorage.setItem('workDiarySidebarCollapsed', next ? '1' : '0'); } catch (e) { /* ignore */ }
                        });
                    }

                    function isDesktop() {
                        return window.matchMedia('(min-width: 1024px)').matches;
                    }

                    function openSidebar() {
                        if (!sidebar) return;
                        sidebar.classList.remove('-translate-x-full');
                        sidebar.classList.add('translate-x-0');
                        if (sidebarBackdrop) sidebarBackdrop.classList.remove('hidden');
                        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
                    }

                    function closeSidebar() {
                        if (!sidebar || isDesktop()) return;
                        sidebar.classList.add('-translate-x-full');
                        sidebar.classList.remove('translate-x-0');
                        if (sidebarBackdrop) sidebarBackdrop.classList.add('hidden');
                        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
                    }

                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', function () {
                            var open = sidebarToggle.getAttribute('aria-expanded') === 'true';
                            if (open) closeSidebar(); else openSidebar();
                        });
                    }
                    if (sidebarBackdrop) {
                        sidebarBackdrop.addEventListener('click', closeSidebar);
                    }
                    if (sidebar) {
                        // Auf Mobile nach Klick auf Nav-Link schließen
                        sidebar.addEventListener('click', function (event) {
                            var link = event.target.closest('a');
                            if (link && !isDesktop()) closeSidebar();
                        });
                    }
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') closeSidebar();
                    });
                    window.addEventListener('resize', function () {
                        // Beim Übergang zu Desktop Backdrop ausblenden, Sidebar via lg:translate-x-0 wieder sichtbar
                        if (isDesktop() && sidebarBackdrop) {
                            sidebarBackdrop.classList.add('hidden');
                            if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
                        }
                    });
                })();
            </script>
            @stack('scripts')
    </body>
</html>
