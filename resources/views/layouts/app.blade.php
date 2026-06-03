<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="geocode-url" content="{{ route('api.internal.geocode') }}">

        {{-- Font-Preloads: starten den Download von IBM Plex Sans (400/600),
             Space Grotesk (700) und Material Symbols PARALLEL zum CSS-Parsing.
             Ohne diese Preloads sieht der Browser die @font-face-Deklarationen
             erst, nachdem das app.css geladen und ausgewertet ist — dadurch
             entstehen sichtbare Layout-Shifts und unrenderte Material-Symbol-
             Ligaturen (z. B. "task_alt" als Text statt Icon). --}}
        @php
            $fontKeys = [
                'node_modules/@fontsource/ibm-plex-sans/files/ibm-plex-sans-latin-400-normal.woff2',
                'node_modules/@fontsource/ibm-plex-sans/files/ibm-plex-sans-latin-600-normal.woff2',
                'node_modules/@fontsource/space-grotesk/files/space-grotesk-latin-700-normal.woff2',
                'node_modules/material-symbols/material-symbols-outlined.woff2',
            ];
            $fontPreloads = [];
            $hotFile = public_path('hot');
            $manifestFile = public_path('build/manifest.json');
            if (is_file($hotFile)) {
                // Dev-Modus: Vite serviert die Fonts direkt aus node_modules/.
                $devUrl = rtrim((string) @file_get_contents($hotFile), "\n\r ");
                foreach ($fontKeys as $key) {
                    $fontPreloads[] = $devUrl . '/' . $key;
                }
            } elseif (is_file($manifestFile)) {
                $manifest = json_decode((string) @file_get_contents($manifestFile), true) ?: [];
                foreach ($fontKeys as $key) {
                    if (isset($manifest[$key]['file'])) {
                        $fontPreloads[] = asset('build/' . $manifest[$key]['file']);
                    }
                }
            }
        @endphp
        @foreach ($fontPreloads as $href)
            <link rel="preload" as="font" type="font/woff2" href="{{ $href }}" crossorigin>
        @endforeach

        {{-- Theme + Anti-Flash: Inline-Skript läuft VOR jeglichem CSS,
             damit data-theme synchron beim ersten Paint passt. Vorher
             war data-theme="dim" hartcodiert; bei Light-Usern flackerte
             es bei jedem Seitenwechsel dunkel → hell. --}}
        <style>
            /* Frühe Basis vor dem CSS-Bundle, damit der erste Paint nicht
               als unstyled white-on-Times-Roman sichtbar wird, bevor Vite
               das CSS injiziert hat (FOUC im Dev-Modus). */
            html { color-scheme: light dark; background: Canvas; color: CanvasText; }
            html[data-theme="dim"] { color-scheme: dark; background: #1d232a; color: #e7e9ea; }
            html[data-theme="corporate"] { color-scheme: light; background: #ffffff; color: #1f2937; }
            body { font-family: "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }

            /* Anti-Layout-Shift für Material-Symbols-Ligaturen:
               Vor dem Laden der Icon-Font würden die Ligatur-Codes wie
               "task_alt" als reiner Text sichtbar werden und Buttons/Zellen
               aufblähen. Mit visibility:hidden + reservierter 1em×1em-Box
               bleibt der finale Icon-Platz erhalten, ohne dass der Text-Code
               flackert. Sobald `document.fonts.ready` aufgelöst ist, setzt
               das Inline-Skript die Klasse `fonts-loaded` und die Icons
               werden sichtbar. */
            .material-symbols-outlined {
                visibility: hidden;
                display: inline-block;
                width: 1em;
                height: 1em;
                line-height: 1;
                overflow: hidden;
                vertical-align: middle;
            }
            html.fonts-loaded .material-symbols-outlined {
                visibility: visible;
                width: auto;
                height: auto;
                overflow: visible;
            }
        </style>
        <script>
            (function () {
                var savedTheme = localStorage.getItem('workDiaryTheme');
                var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = savedTheme || (prefersLight ? 'corporate' : 'dim');
                var root = document.documentElement;
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';

                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(function () {
                        document.documentElement.classList.add('fonts-loaded');
                    });
                } else {
                    // Browser ohne Font-Loading-API: Klasse direkt setzen,
                    // damit Icons nicht permanent unsichtbar bleiben.
                    document.documentElement.classList.add('fonts-loaded');
                }
            })();
        </script>
        <title>@yield('title', isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary'))</title>

        {{-- Favicon-/App-Icon-Set (Multi-Size ICO + PNG-Varianten für moderne Browser, iOS und PWA). --}}
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/logo/workdiary-mark-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/logo/workdiary-mark-192.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo/workdiary-mark-192.png') }}">

        {{-- PWA: Manifest + Theme-Color + iOS-Hinweise. --}}
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" content="#1d232a" media="(prefers-color-scheme: dark)">
        <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="workDiary">
        <meta name="application-name" content="workDiary">

        {{-- Material Symbols werden lokal über @fontsource im App-Bundle (resources/css/app.css)
             ausgeliefert; kein externer Fallback nötig. --}}

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
            /* 3-stufiger Header über CSS Container Queries statt Viewport-
               Breakpoints. Vorteil: das Layout reagiert auf den tatsächlich
               verfügbaren Header-Platz (also nach Abzug der Sidebar) und nicht
               auf die Bildschirmbreite. Dadurch greift das 3-Spalten-Layout
               erst dann, wenn auch zusätzliche header-rechts-Pillen (z. B.
               die Stempeluhr-Anzeige) noch reinpassen — keine Überschneidung
               mit dem zentrierten Zeitraum-Element mehr.

               Stufen:
               - <600cqi:           gestapelt (3 Zeilen)
               - 600-1399cqi:       zwei Zeilen ("left right" oben, "center" unten)
               - >=1400cqi:         eine Zeile ("left center right") */
            #app-header { container-type: inline-size; container-name: app-header; }
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
            .header-dropdown-panel {
                scroll-padding-block: .5rem;
            }
            .header-menu-list.menu {
                gap: .35rem;
            }
            .header-menu-list.menu :where(li) > a,
            .header-menu-list.menu :where(li) > form > button,
            .header-menu-list.menu :where(li) > details > summary {
                min-height: 2.75rem;
                border-radius: var(--radius-field, .5rem);
                padding: .65rem .75rem;
            }
            .header-menu-list.menu :where(li) > details > ul {
                display: flex;
                flex-direction: column;
                gap: .25rem;
                margin-top: .25rem;
                padding-left: .5rem;
            }
            .header-menu-list.menu :where(li) > details > ul :where(li) > a {
                min-height: 2.55rem;
                padding-block: .55rem;
            }
            @media (max-width: 639px), (hover: none) and (pointer: coarse) {
                .header-row {
                    justify-items: stretch;
                }
                .header-row .header-left,
                .header-row .header-center,
                .header-row .header-right {
                    width: 100%;
                }
                .header-row .header-right {
                    justify-content: center;
                }
                .header-dropdown-panel {
                    position: fixed !important;
                    inset-inline: .5rem !important;
                    top: calc(var(--app-header-h) + .35rem) !important;
                    bottom: calc(var(--app-footer-h) + .5rem + env(safe-area-inset-bottom)) !important;
                    height: calc(100vh - var(--app-header-h) - var(--app-footer-h) - 1.35rem - env(safe-area-inset-bottom)) !important;
                    height: calc(100dvh - var(--app-header-h) - var(--app-footer-h) - 1.35rem - env(safe-area-inset-bottom)) !important;
                    width: auto !important;
                    max-height: none !important;
                    margin-top: 0 !important;
                    overscroll-behavior: contain;
                    overflow: auto !important;
                    overflow-x: hidden !important;
                    overflow-y: auto !important;
                    padding-bottom: max(.5rem, env(safe-area-inset-bottom));
                    -webkit-overflow-scrolling: touch;
                    touch-action: pan-y;
                    z-index: 70 !important;
                }
                .dropdown.dropdown-open > .header-dropdown-panel,
                .dropdown:focus-within > .header-dropdown-panel {
                    display: block !important;
                    pointer-events: auto;
                }
                .header-dropdown-panel.header-menu-list.menu {
                    display: none;
                    flex-direction: column;
                    align-content: stretch;
                }
                .dropdown.dropdown-open > .header-dropdown-panel.header-menu-list.menu,
                .dropdown:focus-within > .header-dropdown-panel.header-menu-list.menu {
                    display: flex !important;
                }
            }
            @container app-header (min-width: 600px) {
                .header-row {
                    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                    grid-template-areas: "left right" "center center";
                    justify-items: stretch;
                }
                .header-row .header-left   { justify-self: start; }
                .header-row .header-right  { justify-self: end; flex-wrap: nowrap; justify-content: flex-end; }
                .header-row .header-center { justify-self: center; }
            }
            @container app-header (min-width: 1400px) {
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
            body.sidebar-collapsed #app-sidebar details.sidebar-section-collapsible > ul,
            body.sidebar-collapsed #app-sidebar details.sidebar-section-collapsible > div { display: block !important; }
            body.sidebar-collapsed #app-sidebar .sidebar-section-chevron,
            body.sidebar-collapsed #app-sidebar .sidebar-section-icon { display: none; }
            body.sidebar-collapsed #app-sidebar .sidebar-subgroup-label { display: none; }

            /* Sidebar-Layout: Items scrollen, Footer (Collapse-Button) bleibt sticky
               am unteren Rand der Sidebar sichtbar. */
            #app-sidebar .sidebar-shell {
                display: flex;
                flex-direction: column;
                height: calc(100dvh - var(--app-header-h) - var(--app-footer-h));
            }
            #app-sidebar .sidebar-items {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
            }
            #app-sidebar .sidebar-header {
                flex: 0 0 auto;
                border-bottom: 1px solid var(--color-base-300);
                background-color: var(--color-base-100);
            }
            #app-sidebar .sidebar-footer {
                flex: 0 0 auto;
                border-top: 1px solid var(--color-base-300);
                background-color: var(--color-base-100);
            }
            /* Collapsed-Mode: Header-Padding minimieren, Dropdown-Trigger zentriert. */
            body.sidebar-collapsed #app-sidebar .sidebar-header { padding-left: .25rem; padding-right: .25rem; }
            body.sidebar-collapsed #app-sidebar .sidebar-cta-menu { min-width: 16rem; }
                /* Material Symbols Outlined ist eine Single-Color-Variable-Font und folgt automatisch
                    currentColor — sie funktioniert in jedem DaisyUI-Theme, auf jedem Hintergrund und in
                    allen Button-/Badge-/Alert-Farben ohne weitere CSS-Hacks. Enthält bewusst die
                    kompletten Basis-Styles als Fallback, falls Bundle-CSS temporär ausfällt. */
                .material-symbols-outlined {
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
                     font-feature-settings: 'liga';
                     -webkit-font-feature-settings: 'liga';
                     -webkit-font-smoothing: antialiased;
                     font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                }

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
        <script>window.__translations = @json($jsTranslations ?? []);</script>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                :root { color-scheme: dark; font-family: 'IBM Plex Sans', sans-serif; }
                * { box-sizing: border-box; }
                body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #082f49 0%, #0f172a 45%, #111827 100%); color: #e2e8f0; }
            </style>
        @endif
        {{-- Branding-Overrides für Primär-/Akzentfarbe. Schreiben die
             DaisyUI-Theme-Tokens dynamisch um – wirkt damit überall,
             wo `text-primary`, `btn-primary` etc. verwendet werden. --}}
        @if (isset($branding) && $branding)
            @php
                $_brandPrimary = $branding->primaryColor();
                $_brandAccent = $branding->accentColor();
            @endphp
            @if ($_brandPrimary || $_brandAccent)
                <style>
                    :root {
                        @if ($_brandPrimary) --color-primary: {{ $_brandPrimary }}; @endif
                        @if ($_brandAccent) --color-accent: {{ $_brandAccent }}; @endif
                    }
                </style>
            @endif
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
            $_authUser = Auth::user();
            $canAccessLegacy = $_authUser instanceof \App\Models\User ? $_authUser->canAccessLegacy() : false;
            $canAccessNew = $_authUser instanceof \App\Models\User ? $_authUser->canAccessNew() : false;
            $showModeSwitch = $legacyConfigured && $canAccessLegacy && $canAccessNew;

            // Org-Switcher: nur für Admins, und nur wenn überhaupt
            // mehrere Organisationen existieren. Aktive Org kommt aus dem
            // (via SetOrganizationContext-Middleware bereits aufgelösten)
            // Container-Binding currentOrganization.
            $_isGlobalAdmin = $_authUser instanceof \App\Models\User && $_authUser->isAdmin();
            // Nur AKTIVE Organisationen im Header-Switcher anbieten; deaktivierte
            // dürfen nicht als Kontext gewählt werden, bis sie über die Verwaltung
            // wieder aktiviert wurden.
            $_orgList = $_isGlobalAdmin
                ? \App\Models\Organization::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect();
            $_activeOrg = app()->bound('currentOrganization') ? app('currentOrganization') : null;
            $_activeOrgId = $_activeOrg ? (int) $_activeOrg->id : null;
            $showOrgSwitch = $_isGlobalAdmin && $_orgList->count() > 1;
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
                        @php
                            $_brandLogo = isset($branding) && $branding ? $branding->logoUrl() : null;
                            $_brandName = isset($branding) && $branding ? $branding->appName() : 'WorkDiary';
                        @endphp
                        @if ($_brandLogo)
                            <img src="{{ $_brandLogo }}" alt="{{ $_brandName }}" class="h-9 w-auto max-w-40 object-contain shrink-0">
                        @else
                            <span class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary transition group-hover:opacity-80 shrink-0">{{ $_brandName }}</span>
                        @endif
                        <span class="text-base-content/40">/</span>
                        <span class="font-['Space_Grotesk'] font-semibold text-base-content truncate">@yield('nav-title', __('Auftragsbuch'))</span>
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
                                    ['route' => 'suppliers.index',          'label' => __('Lieferanten'),    'icon' => 'local_shipping',   'modal' => false, 'matches' => ['suppliers.*']],
                                    ['route' => 'projects.index',           'label' => __('Projekte'),       'icon' => 'folder_special',   'modal' => false, 'matches' => ['projects.*']],
                                    ['route' => 'invoices.index',           'label' => __('Rechnungen & Belege'), 'icon' => 'receipt_long',     'modal' => false, 'matches' => ['invoices.*', 'lexoffice.vouchers.*']],
                                    ['route' => 'lexoffice.articles.index', 'label' => __('Produkte & Leistungen'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['lexoffice.articles.*']],
                                    ['route' => 'events.index',             'label' => __('Veranstaltungen'),'icon' => 'event',            'modal' => false, 'matches' => ['events.*']],
                                    ['route' => 'flex.index',               'label' => __('Gleitzeit'),      'icon' => 'hourglass_top',    'modal' => false, 'matches' => ['flex.*']],
                                    ['route' => 'archive.index',            'label' => __('Archiv'),         'icon' => 'inventory_2',      'modal' => false, 'matches' => ['archive.*']],
                                ];

                            $manageNavItems = [];
                            $adminNavItems  = [];
                            // Admin-/Verwaltungs-Menü: sowohl Legacy-Admins (ID ≤ 3 bzw.
                            // Namens-Fallback) als auch echte App-Admins (Spatie-Rolle
                            // Admin) erhalten Zugriff. Sonst sieht ein frisch angelegter
                            // Admin ohne Legacy-ID die Verwaltung nicht.
                            $isAppAdmin = $isLegacyAdmin || $_isGlobalAdmin;
                            if ($isAppAdmin) {
                                if ($isLegacyMode) {
                                    $manageNavItems[] = ['route' => 'legacy.users.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
                                }
                                if (! $isLegacyMode) {
                                    $manageNavItems[] = ['route' => 'holidays.index',     'label' => __('Feiertage'),   'icon' => 'celebration',     'modal' => false];
                                    $manageNavItems[] = ['route' => 'qualifications.index',         'label' => __('Qualifikationen'),  'icon' => 'workspace_premium','modal' => false];
                                    $manageNavItems[] = ['route' => 'rooms.index',                   'label' => __('Räume'),             'icon' => 'meeting_room',     'modal' => false];
                                    $manageNavItems[] = ['route' => 'event-categories.index',        'label' => __('Veranstaltungs-Kategorien'), 'icon' => 'category', 'modal' => false];
                                    $manageNavItems[] = ['route' => 'shift-types.index',             'label' => __('Schichttypen'),     'icon' => 'work_history',     'modal' => false];
                                    $manageNavItems[] = ['route' => 'materials.index',               'label' => __('Materialien'),      'icon' => 'inventory',        'modal' => false];
                                    $manageNavItems[] = ['route' => 'tags.index',                    'label' => __('Tags'),             'icon' => 'label',            'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.organizations.index',     'label' => __('Organisationen'),   'icon' => 'corporate_fare',   'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.branding.edit',           'label' => __('Branding'),         'icon' => 'palette',          'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.entry-types.index',        'label' => __('Eintragstypen'),    'icon' => 'category',         'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.classifications.index',    'label' => __('Klassifikationen'), 'icon' => 'category_search',  'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.classification-requirements.index', 'label' => __('Pflichtregeln'), 'icon' => 'rule_settings', 'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.branch-profiles.index',    'label' => __('Branchenprofile'),  'icon' => 'storefront',       'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.expense-categories.index',  'label' => __('Spesenkategorien'), 'icon' => 'receipt_long',     'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.per-diem-rates.index',      'label' => __('Verpflegungspauschalen'), 'icon' => 'restaurant_menu',  'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.automations.index',         'label' => __('Automatisierungen'), 'icon' => 'bolt',             'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.data.index',                'label' => __('Datentransfer'),    'icon' => 'sync_alt',         'modal' => false];
                                    if (\Illuminate\Support\Facades\Route::has('admin.remote-support.pending.index')) {
                                        $_rsOrg = $_authUser?->organization;
                                        $_rsPending = $_rsOrg !== null
                                            ? \App\Models\RemotePendingSession::query()
                                                ->where('organization_id', $_rsOrg->id)
                                                ->where('status', \App\Models\RemotePendingSession::STATUS_OPEN)
                                                ->count()
                                            : 0;
                                        $adminNavItems[] = ['route' => 'admin.remote-support.pending.index', 'label' => __('Fernwartung – Inbox'), 'icon' => 'inbox', 'modal' => false, 'badge' => $_rsPending];
                                    }
                                }
                                if (! $isLegacyMode && \Illuminate\Support\Facades\Gate::allows('manage-access')) {
                                    $adminNavItems[] = ['route' => 'admin.access.index',             'label' => __('access.title.hub'), 'icon' => 'admin_panel_settings', 'modal' => false];
                                }
                                if (! $isLegacyMode) {
                                    $adminNavItems[] = ['route' => 'audit.index',                       'label' => __('Audit-Log'),        'icon' => 'fact_check',       'modal' => false];
                                    $adminNavItems[] = ['route' => 'admin.plugins.index',                'label' => __('Plugins'),          'icon' => 'extension',        'modal' => false];
                                    $adminNavItems[] = ['route' => 'admin.plugin-errors.index',          'label' => __('Plugin-Fehler'),    'icon' => 'bug_report',       'modal' => false];
                                }
                                $adminNavItems[] = ['route' => 'admin.legacy-migration.index',      'label' => __('Legacy-Migration'), 'icon' => 'sync_alt',         'modal' => false];
                            }
                            if (! $isLegacyMode && \Illuminate\Support\Facades\Gate::allows('manage-members')) {
                                $manageNavItems[] = ['route' => 'org.members.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
                            } elseif (! $isLegacyMode && $_authUser instanceof \App\Models\User && $_authUser->isAdmin()) {
                                // Globaler Admin ohne Org-Kontext: Eintrag verlinkt auf die
                                // Organisations-Verwaltung, damit der Admin sich (oder eine
                                // Organisation) zuordnen kann, bevor Mitglieder gepflegt werden.
                                $manageNavItems[] = ['route' => 'admin.organizations.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
                            }
                            if (! $isLegacyMode) {
                                $manageNavItems[] = ['route' => 'activity-categories.index', 'label' => __('Tätigkeitskategorien'), 'icon' => 'category', 'modal' => false];
                            }

                            $userNavItems = [];
                            if (! $isLegacyMode) {
                                $userNavItems[] = ['route' => 'account.profile.edit',  'label' => __('Profil bearbeiten'), 'modal' => true];
                                $userNavItems[] = ['route' => 'account.work-schedule', 'label' => __('Arbeitszeit-Modell'), 'modal' => true];
                                $userNavItems[] = ['route' => 'account.calendar.show', 'label' => __('Kalender-Abo'), 'modal' => false];
                                $userNavItems[] = ['route' => 'bookmarks.index',       'label' => __('Lesezeichen'), 'modal' => false];
                            } else {
                                $userNavItems[] = ['route' => 'legacy.account.password.edit', 'label' => __('Passwort ändern'), 'modal' => true];
                            }
                            $userNavItems[] = ['route' => 'profile.api-tokens.index', 'label' => __('API-Tokens'), 'modal' => false];

                            $isAdminActive  = collect($adminNavItems)->contains(fn ($i) => request()->routeIs($i['route'])) || request()->routeIs('admin.access.*') || request()->routeIs('admin.imports.*') || request()->routeIs('admin.data.*') || request()->routeIs('admin.remote-support.*');
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
                                    'collapsible' => true,
                                    'items'       => [
                                        ['route' => 'today.show',      'label' => __('Heute'),         'icon' => 'today',             'modal' => false, 'matches' => ['today.show']],
                                        ['route' => $indexRoute,       'label' => __('Arbeitsliste'),  'icon' => 'list_alt',          'modal' => false, 'matches' => [$indexRoute, 'diary.*']],
                                        ['route' => 'week.index',      'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week','modal' => false, 'matches' => ['week.index']],
                                        ['route' => 'kanban.index',    'label' => __('Kanban'),        'icon' => 'view_kanban',       'modal' => false, 'matches' => ['kanban.index']],
                                        ['route' => 'attendance.index','label' => __('Stempeluhr'),    'icon' => 'punch_clock',       'modal' => false, 'matches' => ['attendance.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'plan',
                                    'label'       => __('Planung'),
                                    'collapsible' => true,
                                    'items'       => [
                                        ['route' => 'duty-plans.index', 'label' => __('Dienstpläne'),   'icon' => 'event_available', 'modal' => false, 'matches' => ['duty-plans.*']],
                                        ['route' => 'schedule.index',   'label' => __('Schichtplan'),   'icon' => 'schedule',        'modal' => false, 'matches' => ['schedule.*']],
                                        ['route' => 'timesheets.index', 'label' => __('Stundenzettel'), 'icon' => 'description',     'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                                        ['route' => 'flex.index',       'label' => __('Gleitzeit'),     'icon' => 'hourglass_top',   'modal' => false, 'matches' => ['flex.*']],
                                        ['route' => 'tours.index',      'label' => __('Touren'),        'icon' => 'route',           'modal' => false, 'matches' => ['tours.index', 'tours.map', 'tours.create', 'tours.show', 'tours.edit']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'travel-expenses',
                                    'label'       => __('Reisen & Spesen'),
                                    'collapsible' => true,
                                    'items'       => [
                                        ['route' => 'travel-logs.index',    'label' => __('Fahrtenbuch'),            'icon' => 'directions_car',  'modal' => false, 'matches' => ['travel-logs.*']],
                                        ['route' => 'expenses.index',       'label' => __('Spesen'),                 'icon' => 'receipt_long',    'modal' => false, 'matches' => ['expenses.*']],
                                        ['route' => 'per-diem-trips.index', 'label' => __('Verpflegungspauschalen'), 'icon' => 'restaurant_menu', 'modal' => false, 'matches' => ['per-diem-trips.*']],
                                        ...($_isGlobalAdmin ? [
                                            ['route' => 'expense-approvals.inbox', 'label' => __('Spesen-Genehmigung'), 'icon' => 'fact_check', 'modal' => false, 'matches' => ['expense-approvals.*']],
                                        ] : []),
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'fleet',
                                    'label'       => __('Fuhrpark'),
                                    'collapsible' => true,
                                    'items'       => [
                                        ...(\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Asset::class) ? [
                                            ['route' => 'assets.index',      'label' => __('Objekte & Assets'), 'icon' => 'precision_manufacturing', 'modal' => false, 'matches' => ['assets.*']],
                                        ] : []),
                                        ['route' => 'vehicles.index',    'label' => __('Fahrzeuge'),       'icon' => 'directions_car',  'modal' => false, 'matches' => ['vehicles.*']],
                                        ['route' => 'energy-logs.index', 'label' => __('Tank & Ladelog'),  'icon' => 'local_gas_station','modal' => false, 'matches' => ['energy-logs.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'facility',
                                    'label'       => __('Liegenschaften'),
                                    'collapsible' => true,
                                    'items'       => [
                                        ['route' => 'sites.index',     'label' => __('Standorte'),  'icon' => 'location_on', 'modal' => false, 'matches' => ['sites.*']],
                                        ['route' => 'buildings.index', 'label' => __('Gebäude'),    'icon' => 'apartment',   'modal' => false, 'matches' => ['buildings.*']],
                                        ['route' => 'floors.index',    'label' => __('Geschosse'),  'icon' => 'layers',      'modal' => false, 'matches' => ['floors.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'sales',
                                    'label'       => __('Vertrieb & Abrechnung'),
                                    'collapsible' => true,
                                    'items'       => [
                                        ['route' => 'customers.index', 'label' => __('Kunden'),         'icon' => 'badge',           'modal' => false, 'matches' => ['customers.*']],
                                        ['route' => 'suppliers.index', 'label' => __('Lieferanten'),    'icon' => 'local_shipping',  'modal' => false, 'matches' => ['suppliers.*']],
                                        ['route' => 'projects.index',  'label' => __('Projekte'),       'icon' => 'folder_special',  'modal' => false, 'matches' => ['projects.*']],
                                        ['route' => 'invoices.index',  'label' => __('Rechnungen & Belege'), 'icon' => 'request_quote',   'modal' => false, 'matches' => ['invoices.*', 'lexoffice.vouchers.*']],
                                        ['route' => 'lexoffice.articles.index', 'label' => __('Produkte & Leistungen'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['lexoffice.articles.*']],
                                        ['route' => 'events.index',    'label' => __('Veranstaltungen'),'icon' => 'event',           'modal' => false, 'matches' => ['events.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'reports',
                                    'label'       => __('Auswertungen'),
                                    'collapsible' => true,
                                    'groups'      => [
                                        [
                                            'key'   => 'reports-personal',
                                            'label' => __('Persönlich'),
                                            'icon'  => 'person',
                                            'items' => [
                                                ['route' => 'reports.my-month',     'label' => __('Mein Monat'),    'icon' => 'calendar_view_week',  'modal' => false, 'matches' => ['reports.my-month']],
                                                ['route' => 'reports.my-year',      'label' => __('Mein Jahr'),     'icon' => 'calendar_view_month', 'modal' => false, 'matches' => ['reports.my-year']],
                                                ['route' => 'reports.work-balance', 'label' => __('Arbeitsbilanz'), 'icon' => 'balance',             'modal' => false, 'matches' => ['reports.work-balance']],
                                                ['route' => 'reports.attendance',   'label' => __('Anwesenheit'),   'icon' => 'co_present',          'modal' => false, 'matches' => ['reports.attendance']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'reports-team',
                                            'label' => __('Team'),
                                            'icon'  => 'groups',
                                            'items' => [
                                                ['route' => 'reports.week-by-user',   'label' => __('Woche pro Mitarbeiter'), 'icon' => 'date_range',  'modal' => false, 'matches' => ['reports.week-by-user']],
                                                ['route' => 'reports.month-by-user-team', 'label' => __('Monat pro Mitarbeiter'), 'icon' => 'calendar_view_month', 'modal' => false, 'matches' => ['reports.month-by-user-team']],
                                                ['route' => 'reports.coverage',       'label' => __('Coverage'),              'icon' => 'group_work',  'modal' => false, 'matches' => ['reports.coverage']],
                                                ['route' => 'reports.absences',       'label' => __('Urlaub & Flex'),         'icon' => 'event_busy',  'modal' => false, 'matches' => ['reports.absences']],
                                                ['route' => 'reports.sickness',       'label' => __('Krankheiten'),           'icon' => 'sick',        'modal' => false, 'matches' => ['reports.sickness']],
                                                ['route' => 'reports.qualifications', 'label' => __('Qualifikationen'),       'icon' => 'verified',    'modal' => false, 'matches' => ['reports.qualifications']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'reports-projects',
                                            'label' => __('Projekte & Kunden'),
                                            'icon'  => 'folder_special',
                                            'items' => [
                                                ['route' => 'reports.customers',        'label' => __('Kundenanalyse'),     'icon' => 'bar_chart',  'modal' => false, 'matches' => ['reports.customers']],
                                                ['route' => 'reports.entry-types',      'label' => __('Auftragstypanalyse'), 'icon' => 'stacked_bar_chart', 'modal' => false, 'matches' => ['reports.entry-types']],
                                                ['route' => 'reports.assets',           'label' => __('Produktanalyse'),    'icon' => 'inventory_2', 'modal' => false, 'matches' => ['reports.assets']],
                                                ['route' => 'reports.customer-project', 'label' => __('Kunden & Projekte'), 'icon' => 'pie_chart',  'modal' => false, 'matches' => ['reports.customer-project']],
                                                ['route' => 'reports.project-details',  'label' => __('Projekt-Details'),   'icon' => 'analytics',  'modal' => false, 'matches' => ['reports.project-details']],
                                                ['route' => 'reports.project-inactive', 'label' => __('Inaktive Projekte'), 'icon' => 'folder_off', 'modal' => false, 'matches' => ['reports.project-inactive']],
                                                ['route' => 'reports.operations',       'label' => __('Operations'),        'icon' => 'assignment', 'modal' => false, 'matches' => ['reports.operations']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'reports-resources',
                                            'label' => __('Ressourcen'),
                                            'icon'  => 'inventory_2',
                                            'items' => [
                                                ['route' => 'reports.fleet',     'label' => __('Fuhrpark'),    'icon' => 'directions_car',       'modal' => false, 'matches' => ['reports.fleet']],
                                                ['route' => 'reports.materials', 'label' => __('Materialien'), 'icon' => 'inventory',            'modal' => false, 'matches' => ['reports.materials']],
                                                ['route' => 'reports.on-call',   'label' => __('Notdienst'),   'icon' => 'notifications_active', 'modal' => false, 'matches' => ['reports.on-call']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'reports-finance',
                                            'label' => __('Finanzen & Audit'),
                                            'icon'  => 'request_quote',
                                            'items' => [
                                                ['route' => 'reports.billing',        'label' => __('Abrechnung'),      'icon' => 'request_quote', 'modal' => false, 'matches' => ['reports.billing']],
                                                ['route' => 'reports.expenses',       'label' => __('Spesen'),          'icon' => 'receipt_long',  'modal' => false, 'matches' => ['reports.expenses']],
                                                ['route' => 'reports.audit-activity', 'label' => __('Audit-Aktivität'), 'icon' => 'security',      'modal' => false, 'matches' => ['reports.audit-activity']],
                                            ],
                                        ],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'archive',
                                    'label'       => __('Archiv'),
                                    'collapsible' => true,
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
                                <ul tabindex="0" class="dropdown-content header-dropdown-panel header-menu-list menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
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
                                @foreach ($manageNavItems as $item)
                                    @php $active = request()->routeIs($item['route']); @endphp
                                    <x-icon-btn :icon="$item['icon'] ?? 'tune'"
                                                :tone="$active ? 'primary' : 'ghost'"
                                                size="sm"
                                                :label="$item['label']"
                                                :href="route($item['route'])" />
                                @endforeach
                            @endif

                            @php $archiveActive = request()->routeIs('legacy.archive.*'); @endphp
                            <x-icon-btn icon="inventory_2"
                                        :tone="$archiveActive ? 'primary' : 'ghost'"
                                        size="sm"
                                        :label="__('Archiv')"
                                        :href="route('legacy.archive.index')" />

                            @if (! empty($adminNavItems))
                                @foreach ($adminNavItems as $item)
                                    @php $active = request()->routeIs($item['route']); @endphp
                                    @if (! empty($item['badge']))
                                        <div class="indicator">
                                            <span class="indicator-item badge badge-xs badge-warning">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                            <x-icon-btn :icon="$item['icon'] ?? 'tune'"
                                                        :tone="$active ? 'primary' : 'ghost'"
                                                        size="sm"
                                                        :label="$item['label']"
                                                        :href="route($item['route'])" />
                                        </div>
                                    @else
                                        <x-icon-btn :icon="$item['icon'] ?? 'tune'"
                                                    :tone="$active ? 'primary' : 'ghost'"
                                                    size="sm"
                                                    :label="$item['label']"
                                                    :href="route($item['route'])" />
                                    @endif
                                @endforeach
                            @endif

                            <x-icon-btn icon="add" tone="primary" size="sm"
                                        data-entry-modal-trigger
                                        :href="route($createRoute)"
                                        show-label>{{ __('Neuer Eintrag') }}</x-icon-btn>
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
                                    <label tabindex="0"
                                           class="btn btn-sm btn-square {{ $isManageActive ? 'btn-primary' : 'btn-ghost' }}"
                                           title="{{ __('Verwaltung') }}"
                                           aria-label="{{ __('Verwaltung') }}">
                                        <x-icon name="manage_accounts" class="text-[1.1rem]" />
                                    </label>
                                    <div tabindex="0" class="dropdown-content header-dropdown-panel z-50 w-[min(22rem,calc(100vw-1rem))]! rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        <ul class="header-menu-list menu w-full p-0">
                                        @php
                                            // Gruppierung der Verwaltungs-Einträge in aufklappbare Ordner.
                                            $manageGroups = [
                                                ['label' => __('Personal'), 'icon' => 'group', 'routes' => ['org.members.index', 'legacy.users.index', 'qualifications.index']],
                                                ['label' => __('Planung'), 'icon' => 'event', 'routes' => ['holidays.index', 'shift-types.index', 'rooms.index', 'event-categories.index']],
                                                ['label' => __('Kataloge'), 'icon' => 'category', 'routes' => ['materials.index', 'tags.index', 'activity-categories.index']],
                                            ];
                                            $manageByRoute = collect($manageNavItems)->keyBy('route');
                                            $manageGrouped = collect();
                                            foreach ($manageGroups as $g) {
                                                $items = collect($g['routes'])->map(fn ($r) => $manageByRoute->get($r))->filter()->values();
                                                if ($items->isNotEmpty()) {
                                                    $manageGrouped->push(['label' => $g['label'], 'icon' => $g['icon'], 'items' => $items]);
                                                }
                                            }
                                            $manageGroupedRoutes = $manageGrouped->flatMap(fn ($g) => $g['items']->pluck('route'))->all();
                                            $manageUngrouped = collect($manageNavItems)->reject(fn ($i) => in_array($i['route'], $manageGroupedRoutes, true))->values();
                                        @endphp
                                        @foreach ($manageGrouped as $group)
                                            @php $groupActive = $group['items']->contains(fn ($i) => request()->routeIs($i['route'])); @endphp
                                            <li class="w-full">
                                                <details @if ($groupActive) open @endif>
                                                    <summary class="flex! w-full items-center gap-3 {{ $groupActive ? 'menu-active' : '' }}">
                                                        <x-icon :name="$group['icon']" class="text-[1.1rem] shrink-0" />
                                                        <span class="min-w-0 flex-1 truncate">{{ $group['label'] }}</span>
                                                    </summary>
                                                    <ul>
                                                        @foreach ($group['items'] as $item)
                                                            @php $active = request()->routeIs($item['route']); @endphp
                                                            <li class="w-full">
                                                                <a href="{{ route($item['route']) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                                    <x-icon :name="$item['icon'] ?? 'tune'" class="text-[1.1rem] shrink-0" />
                                                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            </li>
                                        @endforeach
                                        @foreach ($manageUngrouped as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li class="w-full">
                                                <a href="{{ route($item['route']) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                    <x-icon :name="$item['icon'] ?? 'tune'" class="text-[1.1rem] shrink-0" />
                                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            @if (! empty($adminNavItems))
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0"
                                           class="btn btn-sm btn-square {{ $isAdminActive ? 'btn-primary' : 'btn-ghost' }}"
                                           title="{{ __('System') }}"
                                           aria-label="{{ __('System') }}">
                                        <x-icon name="settings" class="text-[1.1rem]" />
                                    </label>
                                    <div tabindex="0" class="dropdown-content header-dropdown-panel z-50 w-[min(22rem,calc(100vw-1rem))]! rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        <ul class="header-menu-list menu w-full p-0">
                                        @php
                                            // Gruppierung der System-Einträge in aufklappbare Ordner.
                                            $adminGroups = [
                                                ['label' => __('Stammdaten'), 'icon' => 'inventory_2', 'routes' => ['admin.organizations.index', 'admin.branding.edit', 'admin.entry-types.index', 'admin.classifications.index', 'admin.classification-requirements.index', 'admin.branch-profiles.index', 'admin.expense-categories.index', 'admin.per-diem-rates.index']],
                                                ['label' => __('Daten & Schnittstellen'), 'icon' => 'sync_alt', 'routes' => ['admin.automations.index', 'admin.data.index', 'admin.remote-support.pending.index']],
                                                ['label' => __('System'), 'icon' => 'settings', 'routes' => ['admin.access.index', 'audit.index', 'admin.plugins.index', 'admin.plugin-errors.index', 'admin.legacy-migration.index']],
                                            ];
                                            $adminByRoute = collect($adminNavItems)->keyBy('route');
                                            $adminGrouped = collect();
                                            foreach ($adminGroups as $g) {
                                                $items = collect($g['routes'])->map(fn ($r) => $adminByRoute->get($r))->filter()->values();
                                                if ($items->isNotEmpty()) {
                                                    $adminGrouped->push(['label' => $g['label'], 'icon' => $g['icon'], 'items' => $items]);
                                                }
                                            }
                                            $groupedRoutes = $adminGrouped->flatMap(fn ($g) => $g['items']->pluck('route'))->all();
                                            $adminUngrouped = collect($adminNavItems)->reject(fn ($i) => in_array($i['route'], $groupedRoutes, true))->values();
                                        @endphp
                                        @foreach ($adminGrouped as $group)
                                            @php
                                                $groupActive = $group['items']->contains(fn ($i) => request()->routeIs($i['route']));
                                                $groupBadge = $group['items']->sum(fn ($i) => $i['badge'] ?? 0);
                                            @endphp
                                            <li class="w-full">
                                                <details @if ($groupActive) open @endif>
                                                    <summary class="flex! w-full items-center gap-3 {{ $groupActive ? 'menu-active' : '' }}">
                                                        <x-icon :name="$group['icon']" class="text-[1.1rem] shrink-0" />
                                                        <span class="min-w-0 flex-1 truncate">{{ $group['label'] }}</span>
                                                        @if ($groupBadge > 0)
                                                            <span class="badge badge-sm badge-warning shrink-0">{{ $groupBadge > 99 ? '99+' : $groupBadge }}</span>
                                                        @endif
                                                    </summary>
                                                    <ul>
                                                        @foreach ($group['items'] as $item)
                                                            @php $active = request()->routeIs($item['route']); @endphp
                                                            <li class="w-full">
                                                                <a href="{{ route($item['route']) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                                    <x-icon :name="$item['icon'] ?? 'tune'" class="text-[1.1rem] shrink-0" />
                                                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                                                    @if (! empty($item['badge']))
                                                                        <span class="badge badge-sm badge-warning shrink-0">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                                                    @endif
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            </li>
                                        @endforeach
                                        @foreach ($adminUngrouped as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li class="w-full">
                                                <a href="{{ route($item['route']) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                    <x-icon :name="$item['icon'] ?? 'tune'" class="text-[1.1rem] shrink-0" />
                                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                                    @if (! empty($item['badge']))
                                                        <span class="badge badge-sm badge-warning shrink-0">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                        </ul>
                                    </div>
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

                        @isset($attendanceCurrent)
                            @if ($attendanceCurrent)
                                <div class="flex items-center gap-1.5 rounded-box border border-success/40 bg-success/10 px-2 py-1 shadow-xs"
                                     title="{{ __('Eingestempelt seit :time', ['time' => $attendanceCurrent->started_at?->format('H:i')]) }}"
                                     x-data="{ s: 0 }"
                                     x-init="s = Math.max(0, Math.floor((Date.now() - new Date('{{ $attendanceCurrent->started_at?->toIso8601String() }}').getTime())/1000)); setInterval(() => s++, 1000);">
                                    <x-icon name="badge" class="text-[1rem] text-success" />
                                    <span class="font-['Space_Grotesk'] text-sm font-semibold tabular-nums text-success"
                                          x-text="String(Math.floor(s/3600)).padStart(2,'0') + ':' + String(Math.floor((s%3600)/60)).padStart(2,'0')">00:00</span>
                                    <form method="POST" action="{{ route('attendance.clock-out') }}" class="leading-none">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-ghost btn-square text-warning" title="{{ __('Ausstempeln') }}" aria-label="{{ __('Ausstempeln') }}">
                                            <x-icon name="logout" />
                                        </button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('attendance.clock-in') }}" class="leading-none">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-success gap-1" title="{{ __('Einstempeln') }}">
                                        <x-icon name="login" class="text-[1rem]" />
                                        <span class="hidden sm:inline">{{ __('Einstempeln') }}</span>
                                    </button>
                                </form>
                            @endif
                        @endisset

                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                            @php
                                $_reminders = $reminderItems ?? [];
                                $_reminderTotal = collect($_reminders)->sum(fn($r) => is_object($r) ? $r->count : (int) ($r['count'] ?? 0));
                                $_reminderHasError = collect($_reminders)->contains(fn($r) => (is_object($r) ? $r->severity : ($r['severity'] ?? '')) === 'error');
                            @endphp
                            @if (! $isLegacyMode)
                            @php
                                $_bookmarks = $userBookmarks ?? collect();
                            @endphp
                            <div class="dropdown dropdown-end">
                                <label tabindex="0"
                                       class="btn btn-sm btn-ghost btn-square"
                                       title="{{ __('Lesezeichen') }}"
                                       aria-label="{{ __('Lesezeichen') }}">
                                    <x-icon name="bookmarks" class="text-base" />
                                </label>
                                <div tabindex="0" class="dropdown-content header-dropdown-panel z-50 mt-2 w-[min(20rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-0 shadow-lg overflow-hidden">
                                    <div class="px-4 py-2 border-b border-base-200 flex items-center justify-between gap-2">
                                        <span class="text-xs uppercase tracking-wider opacity-60">{{ __('Lesezeichen') }}</span>
                                        <a href="{{ route('bookmarks.create') }}?url={{ urlencode(request()->fullUrl()) }}"
                                           data-entry-modal-trigger
                                           class="btn btn-xs btn-ghost"
                                           title="{{ __('Diese Seite merken') }}">
                                            <x-icon name="add" class="text-sm" />
                                            <span>{{ __('Merken') }}</span>
                                        </a>
                                    </div>
                                    <div class="max-h-96 overflow-y-auto">
                                        @forelse ($_bookmarks as $_bm)
                                            <a href="{{ $_bm->url }}"
                                               class="flex items-start gap-3 px-4 py-3 hover:bg-base-200 border-b border-base-200 last:border-b-0">
                                                <span class="material-symbols-outlined text-base" aria-hidden="true">{{ $_bm->icon ?: 'bookmark' }}</span>
                                                <span class="flex-1 min-w-0 text-sm font-medium truncate">{{ $_bm->label }}</span>
                                            </a>
                                        @empty
                                            <div class="px-4 py-6 text-center text-sm opacity-60">
                                                <x-icon name="bookmark_border" class="text-2xl block mb-1 mx-auto" />
                                                {{ __('Noch keine Lesezeichen.') }}
                                            </div>
                                        @endforelse
                                    </div>
                                    <div class="px-4 py-2 border-t border-base-200 text-right">
                                        <a href="{{ route('bookmarks.index') }}" class="text-xs link link-hover opacity-70">{{ __('Verwalten') }} →</a>
                                    </div>
                                </div>
                            </div>
                            @endif
                            @if (! $isLegacyMode)
                            <div class="dropdown dropdown-end">
                                <label tabindex="0"
                                       class="btn btn-sm btn-ghost btn-square relative"
                                       title="{{ __('Erinnerungen') }}"
                                       aria-label="{{ __('Erinnerungen') }}">
                                    <x-icon name="notifications" class="text-base" />
                                    @if ($_reminderTotal > 0)
                                        <span class="absolute -top-0.5 -right-0.5 badge badge-xs {{ $_reminderHasError ? 'badge-error' : 'badge-warning' }} text-[0.6rem] font-semibold">
                                            {{ $_reminderTotal > 99 ? '99+' : $_reminderTotal }}
                                        </span>
                                    @endif
                                </label>
                                <div tabindex="0" class="dropdown-content header-dropdown-panel z-50 mt-2 w-[min(22rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-0 shadow-lg overflow-hidden">
                                    <div class="px-4 py-2 border-b border-base-200 flex items-center justify-between">
                                        <span class="text-xs uppercase tracking-wider opacity-60">{{ __('Erinnerungen') }}</span>
                                        @if (count($_reminders) > 0)
                                            <span class="text-[0.65rem] opacity-50">{{ count($_reminders) }} {{ __('offen') }}</span>
                                        @endif
                                    </div>
                                    <div class="max-h-96 overflow-y-auto">
                                        @forelse ($_reminders as $_reminder)
                                            @php
                                                $_r = is_object($_reminder) ? $_reminder->toArray() : $_reminder;
                                            @endphp
                                            <a href="{{ $_r['url'] }}"
                                               class="flex items-start gap-3 px-4 py-3 hover:bg-base-200 border-b border-base-200 last:border-b-0">
                                                <span class="material-symbols-outlined text-base
                                                    @if ($_r['severity'] === 'error') text-error
                                                    @elseif ($_r['severity'] === 'warning') text-warning
                                                    @else text-info @endif"
                                                      aria-hidden="true">{{ $_r['icon'] }}</span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-sm font-medium">{{ $_r['title'] }}</span>
                                                    <span class="block text-xs opacity-60 mt-0.5">{{ $_r['description'] }}</span>
                                                </span>
                                            </a>
                                        @empty
                                            <div class="px-4 py-6 text-center text-sm opacity-60">
                                                <x-icon name="check_circle" class="text-success text-2xl block mb-1 mx-auto" />
                                                {{ __('Alles erledigt.') }}
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            @endif
                            @if (! $isLegacyMode)
                                <button type="button" data-global-search-open
                                        class="btn btn-sm btn-ghost btn-square"
                                        title="{{ __('Globale Suche (Strg+K / Cmd+K)') }}"
                                        aria-label="{{ __('Globale Suche öffnen') }}">
                                    <x-icon name="search" class="text-base" />
                                </button>
                            @endif
                            {{-- Einstellungen-Dropdown: bündelt Theme-Toggle, Sprache,
                                 Modus-Switch (Legacy) und Org-Switch in einem einzigen
                                 Top-Level-Element. Reduziert sichtbare Header-Items von
                                 ~8 auf 5 — verhindert Überschneidung mit dem zentrierten
                                 Zeitraum-Element auf mittleren Viewports. --}}
                            <div class="dropdown dropdown-end">
                                <label tabindex="0"
                                       class="btn btn-sm btn-ghost btn-square"
                                       title="{{ __('Einstellungen') }}"
                                       aria-label="{{ __('Einstellungen') }}">
                                    <x-icon name="tune" class="text-base" />
                                </label>
                                <div tabindex="0" class="dropdown-content header-dropdown-panel z-50 mt-2 w-[min(20rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-0 shadow-lg overflow-hidden">
                                    {{-- Farbschema --}}
                                    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-base-200">
                                        <span class="text-sm font-medium">{{ __('Farbschema') }}</span>
                                        <button type="button"
                                                data-theme-toggle
                                                aria-label="{{ __('Farbschema wechseln') }}"
                                                title="{{ __('Farbschema wechseln') }}"
                                                class="btn btn-xs btn-ghost gap-2">
                                            <span data-theme-label class="text-base leading-none">◐</span>
                                            <span class="text-xs opacity-70">{{ __('Wechseln') }}</span>
                                        </button>
                                    </div>

                                    {{-- Sprache --}}
                                    <div class="px-4 py-3 border-b border-base-200">
                                        <p class="mb-2 text-xs uppercase tracking-wider opacity-60">{{ __('Sprache') }}</p>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($supportedLocales as $code => $locale)
                                                <form method="POST" action="{{ route('locale.switch', $code) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-xs {{ $currentLocale === $code ? 'btn-primary' : 'btn-ghost' }} gap-1.5"
                                                            title="{{ $locale['label'] }}">
                                                        <span class="font-mono text-[0.65rem] font-bold opacity-80">{{ $locale['code'] }}</span>
                                                        <span>{{ $locale['label'] }}</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </div>

                                    @if ($showModeSwitch)
                                        {{-- Legacy-Toggle-Switch (nur sichtbar wenn der User Zugriff auf BEIDE Bereiche hat) --}}
                                        <form method="POST"
                                              action="{{ route('mode.switch', $isLegacyMode ? 'new' : 'legacy') }}"
                                              id="mode-switch-form"
                                              class="flex items-center justify-between gap-3 px-4 py-3 border-b border-base-200">
                                            @csrf
                                            <input type="hidden" name="origin" value="{{ $originRoute }}">
                                            <label for="mode-switch-toggle"
                                                   class="text-sm font-medium cursor-pointer select-none"
                                                   title="{{ __('Modus wechseln') }}">
                                                {{ __('Legacy-Modus') }}
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

                                    @if ($showOrgSwitch && ! $isLegacyMode)
                                        {{-- Org-Switcher: nur fuer globale Admins, nur im neuen Modus sinnvoll. --}}
                                        <form method="POST"
                                              action="{{ route('admin.organizations.switch') }}"
                                              id="org-switch-form"
                                              class="px-4 py-3">
                                            @csrf
                                            <label for="org-switch-select"
                                                   class="block mb-1 text-xs uppercase tracking-wider opacity-60"
                                                   title="{{ __('Aktive Organisation') }}">
                                                {{ __('Aktive Organisation') }}
                                            </label>
                                            <select name="organization_id"
                                                    id="org-switch-select"
                                                    class="select select-bordered select-sm w-full"
                                                    onchange="this.form.submit()"
                                                    aria-label="{{ __('Aktive Organisation waehlen') }}"
                                                    title="{{ __('Aktive Organisation waehlen') }}">
                                                @foreach ($_orgList as $_orgItem)
                                                    <option value="{{ $_orgItem->sqid }}"
                                                            {{ $_activeOrgId === (int) $_orgItem->id ? 'selected' : '' }}>
                                                        {{ $_orgItem->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <div class="dropdown dropdown-end">
                                <label tabindex="0"
                                       class="btn btn-sm gap-1.5 {{ $isUserActive ? 'btn-primary' : 'btn-ghost' }}"
                                       title="{{ Auth::user()->name }}"
                                       aria-label="{{ __('Benutzermenü') }}">
                                    <x-icon name="account_circle" class="text-[1.65rem]" />
                                    <span class="max-w-32 truncate">{{ Auth::user()->name }}</span>
                                </label>
                                <ul tabindex="0" class="dropdown-content header-dropdown-panel header-menu-list menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
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
                                <ul tabindex="0" class="dropdown-content header-dropdown-panel header-menu-list menu z-50 w-[min(10rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-1 shadow">
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
            @if (! $isLegacyMode)
                @include('partials.global-search')
            @endif
        @endauth

        @auth
        @unless ($isLegacyMode)
        {{-- Sidebar: persistent ab lg, sonst Drawer --}}
        <aside id="app-sidebar"
               class="fixed left-0 z-40 -translate-x-full transform border-r border-base-300 shadow-sm transition-transform duration-200 lg:translate-x-0"
               style="top: var(--app-header-h); bottom: var(--app-footer-h);"
               aria-label="{{ __('Hauptnavigation') }}"
               data-sidebar>
            <div class="sidebar-shell">
                @php
                    $createGroups = [
                        [
                            'label' => __('Tagesgeschäft'),
                            'items' => [
                                ['route' => 'diary.create',              'label' => __('Auftrag'),         'icon' => 'assignment'],
                                ['route' => 'time-entries.create',       'label' => __('Zeiteintrag'),     'icon' => 'timer'],
                                ['route' => 'timesheets.create',         'label' => __('Stundenzettel'),   'icon' => 'description'],
                                ['route' => 'admin-time-entries.create', 'label' => __('Verwaltungszeit'), 'icon' => 'schedule'],
                            ],
                        ],
                        [
                            'label' => __('Planung'),
                            'items' => [
                                ['route' => 'duty-plans.create',     'label' => __('Dienstplan'),  'icon' => 'event_available'],
                                ['route' => 'vacations.create',      'label' => __('Urlaub'),      'icon' => 'beach_access'],
                                ['route' => 'events.create',         'label' => __('Veranstaltung'),'icon' => 'event'],
                                ['route' => 'travel-logs.create',    'label' => __('Fahrtbuch'),   'icon' => 'route'],
                                ['route' => 'expenses.create',       'label' => __('Spese'),       'icon' => 'receipt_long'],
                                ['route' => 'tours.create',          'label' => __('Tour'),        'icon' => 'directions_bus'],
                            ],
                        ],
                        [
                            'label' => __('Fuhrpark'),
                            'items' => [
                                ['route' => 'vehicles.create',    'label' => __('Fahrzeug'),     'icon' => 'directions_car'],
                                ['route' => 'energy-logs.create', 'label' => __('Tank-/Ladelog'),'icon' => 'local_gas_station'],
                            ],
                        ],
                        [
                            'label' => __('Stammdaten'),
                            'items' => [
                                ['route' => 'customers.create',      'label' => __('Kunde'),        'icon' => 'badge'],
                                ['route' => 'projects.create',       'label' => __('Projekt'),      'icon' => 'folder_special'],
                                ['route' => 'shift-types.create',    'label' => __('Schichttyp'),   'icon' => 'label'],
                                ['route' => 'qualifications.create', 'label' => __('Qualifikation'),'icon' => 'verified'],
                            ],
                        ],
                    ];
                    // Nicht registrierte Routen herausfiltern, leere Gruppen entfernen.
                    $createGroups = collect($createGroups)
                        ->map(function ($g) {
                            $g['items'] = collect($g['items'])->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']))->values()->all();
                            return $g;
                        })
                        ->filter(fn ($g) => ! empty($g['items']))
                        ->values()
                        ->all();
                @endphp

                <div class="sidebar-header px-2 py-3">
                    <div class="dropdown dropdown-bottom dropdown-start w-full">
                        <div tabindex="0" role="button"
                             class="sidebar-cta btn btn-sm btn-primary w-full gap-2"
                             title="{{ __('Neu …') }}"
                             aria-label="{{ __('Neuen Eintrag erstellen') }}">
                            <x-icon name="add_circle" />
                            <span class="sidebar-cta-text flex-1 text-left">{{ __('Neu …') }}</span>
                            <x-icon name="expand_more" class="sidebar-cta-text text-[1.1rem] opacity-80" />
                        </div>
                        <ul tabindex="0"
                            class="sidebar-cta-menu dropdown-content menu menu-sm z-50 mt-2 w-64 max-h-[70vh] overflow-y-auto rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
                            @foreach ($createGroups as $gi => $group)
                                @if ($gi > 0)
                                    <li><div class="divider my-1"></div></li>
                                @endif
                                <li class="menu-title">
                                    <span class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/55">{{ $group['label'] }}</span>
                                </li>
                                @foreach ($group['items'] as $item)
                                    <li>
                                        <a href="{{ route($item['route']) }}"
                                           data-entry-modal-trigger
                                           class="flex items-center gap-3"
                                           title="{{ $item['label'] }}">
                                            <x-icon :name="$item['icon'] ?? 'add'" />
                                            <span class="truncate">{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="sidebar-items flex flex-col gap-4 px-2 py-3">
                    <div class="flex flex-col gap-4">
                        @foreach ($sidebarSections as $section)
                            @php
                                $sectionGroups = $section['groups'] ?? null;
                                $sectionItems  = $sectionGroups
                                    ? collect($sectionGroups)->flatMap(fn ($g) => $g['items'] ?? [])->all()
                                    : ($section['items'] ?? []);
                                $sectionActive = collect($sectionItems)->contains(
                                    fn ($i) => collect($i['matches'] ?? [$i['route']])->contains(fn ($m) => request()->routeIs($m))
                                );
                            @endphp
                            <details class="sidebar-section sidebar-section-collapsible"
                                     data-sidebar-section-key="{{ $section['key'] }}"
                                     @if ($sectionActive) open @endif>
                                <summary class="sidebar-section-summary">
                                    <x-icon :name="$section['icon'] ?? 'folder'" class="sidebar-section-icon" />
                                    <span data-sidebar-label class="flex-1 truncate">{{ $section['label'] }}</span>
                                    <x-icon name="expand_more" class="sidebar-section-chevron" />
                                </summary>
                                @if ($sectionGroups)
                                    <div class="flex flex-col gap-1 pt-1">
                                        @foreach ($sectionGroups as $group)
                                            @php
                                                $groupActive = collect($group['items'] ?? [])->contains(
                                                    fn ($i) => collect($i['matches'] ?? [$i['route']])->contains(fn ($m) => request()->routeIs($m))
                                                );
                                            @endphp
                                            <div class="sidebar-subgroup"
                                                 data-sidebar-subgroup-key="{{ $group['key'] ?? '' }}">
                                                <div class="sidebar-subgroup-label flex items-center gap-2 px-2 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-base-content/60">
                                                    <x-icon :name="$group['icon'] ?? 'label'" class="text-[0.95rem] opacity-70" />
                                                    <span data-sidebar-label class="truncate">{{ $group['label'] }}</span>
                                                </div>
                                                <ul class="menu menu-sm w-full gap-0.5 p-0">
                                                    @foreach (($group['items'] ?? []) as $item)
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
                                        @endforeach
                                    </div>
                                @else
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
                                @endif
                            </details>
                        @endforeach
                    </div>
                </div>

                <div class="sidebar-footer px-2 py-2">
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
        <script>
            (function () {
                function closeDropdown(dropdown) {
                    dropdown.classList.remove('dropdown-open');
                    var trigger = dropdown.querySelector(':scope > [tabindex="0"]');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                }

                function closeOthers(current) {
                    document.querySelectorAll('.dropdown.dropdown-open').forEach(function (dropdown) {
                        if (dropdown !== current) closeDropdown(dropdown);
                    });
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('.dropdown > [tabindex="0"]');
                    if (trigger) {
                        if (trigger.classList.contains('dropdown-content')) return;

                        var dropdown = trigger.closest('.dropdown');
                        if (!dropdown || !dropdown.querySelector('.header-dropdown-panel')) return;

                        event.preventDefault();
                        var open = dropdown.classList.contains('dropdown-open');
                        closeOthers(dropdown);
                        dropdown.classList.toggle('dropdown-open', !open);
                        trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
                        return;
                    }

                    if (!event.target.closest('.dropdown.dropdown-open')) {
                        closeOthers(null);
                    }
                }, true);

                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') return;
                    closeOthers(null);
                });
            })();
        </script>

        @if (session('mode_toast'))
        <div id="mode-toast"
             class="fixed bottom-24 left-1/2 z-200 -translate-x-1/2 translate-y-0 opacity-100 transition-all duration-500"
             aria-live="polite">
            <div class="flex items-center gap-3 rounded-2xl border border-base-300 bg-base-100/90 px-5 py-3 text-sm shadow-xl backdrop-blur-sm">
                <span class="text-base"><x-icon :name="$effectiveMode === 'legacy' ? 'folder_open' : 'auto_awesome'" /></span>
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
        @auth
            <x-demo-banner :organization="$_activeOrg" />
        @endauth
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
                &copy; {{ date('Y') }} {{ isset($branding) && $branding ? $branding->appName() : 'WorkDiary' }}. {{ __('Alle Rechte vorbehalten.') }}
            </div>
        </footer>

        <x-modal id="action-confirm-dialog"
                 :embedded="false"
                 size="md"
                 tone="warning"
                 icon="help"
                 :title="__('Aktion bestätigen')"
                 headerId="action-confirm-header"
                 iconWrapId="action-confirm-icon-wrap"
                 iconId="action-confirm-icon"
                 titleId="action-confirm-title">
            <p id="action-confirm-message" class="text-sm text-base-content/75">{{ __('Möchtest du diese Aktion wirklich ausführen?') }}</p>

            <x-slot:actions>
                <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
                    <x-icon name="close" /> {{ __('Abbrechen') }}
                </button>
                <button id="action-confirm-submit" type="button" class="btn btn-error gap-2">
                    <x-icon name="check" /> {{ __('Ausführen') }}
                </button>
            </x-slot:actions>
        </x-modal>

        <x-modal id="action-notify-dialog"
                 :embedded="false"
                 size="md"
                 tone="info"
                 icon="info"
                 :title="__('Hinweis')"
                 headerId="action-notify-header"
                 iconWrapId="action-notify-icon-wrap"
                 iconId="action-notify-icon"
                 titleId="action-notify-title">
            <p id="action-notify-message" class="whitespace-pre-line text-sm text-base-content/80"></p>

            <x-slot:actions>
                <button id="action-notify-ok" type="button" class="btn btn-primary gap-2" data-entry-modal-close>
                    <x-icon name="check" /> {{ __('OK') }}
                </button>
            </x-slot:actions>
        </x-modal>

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
                    var confirmHeader = document.getElementById('action-confirm-header');
                    var confirmIconWrap = document.getElementById('action-confirm-icon-wrap');
                    var confirmIcon = document.getElementById('action-confirm-icon');
                    var notifyDialog = document.getElementById('action-notify-dialog');
                    var notifyTitle = document.getElementById('action-notify-title');
                    var notifyMessage = document.getElementById('action-notify-message');
                    var notifyHeader = document.getElementById('action-notify-header');
                    var notifyIconWrap = document.getElementById('action-notify-icon-wrap');
                    var notifyIcon = document.getElementById('action-notify-icon');
                    var notifyOk = document.getElementById('action-notify-ok');
                    var pendingForm = null;
                    var pendingSubmitter = null;
                    var pendingResolve = null;
                    var pendingNotifyResolve = null;

                    var toneAccents = {
                        primary: { header: 'from-primary/15 via-primary/5 to-transparent', icon: 'bg-primary/15 text-primary' },
                        success: { header: 'from-success/15 via-success/5 to-transparent', icon: 'bg-success/15 text-success' },
                        warning: { header: 'from-warning/15 via-warning/5 to-transparent', icon: 'bg-warning/15 text-warning' },
                        error:   { header: 'from-error/15 via-error/5 to-transparent',     icon: 'bg-error/15 text-error' },
                        info:    { header: 'from-info/15 via-info/5 to-transparent',       icon: 'bg-info/15 text-info' },
                        ghost:   { header: 'from-base-300/40 via-base-200/30 to-transparent', icon: 'bg-base-300 text-base-content/70' }
                    };
                    var toneClasses = [];
                    Object.keys(toneAccents).forEach(function (k) {
                        toneAccents[k].header.split(' ').forEach(function (c) { if (toneClasses.indexOf(c) < 0) toneClasses.push(c); });
                        toneAccents[k].icon.split(' ').forEach(function (c) { if (toneClasses.indexOf(c) < 0) toneClasses.push(c); });
                    });

                    function applyTone(headerEl, iconWrapEl, tone) {
                        var accent = toneAccents[tone] || toneAccents.warning;
                        toneClasses.forEach(function (c) {
                            headerEl.classList.remove(c);
                            iconWrapEl.classList.remove(c);
                        });
                        accent.header.split(' ').forEach(function (c) { headerEl.classList.add(c); });
                        accent.icon.split(' ').forEach(function (c) { iconWrapEl.classList.add(c); });
                    }

                    // Rendert einen Icon-Bezeichner in das Icon-Element.
                    // Akzeptiert Material-Symbol-Namen (a-z0-9_) ODER beliebige
                    // Emojis/Roh-HTML als Backwards-Compat.
                    function renderIcon(el, value) {
                        if (!el) return;
                        var v = (value == null) ? '' : String(value);
                        if (v !== '' && /^[a-z0-9_]+$/.test(v)) {
                            el.innerHTML = '<span class="material-symbols-outlined">' + v + '</span>';
                        } else {
                            el.textContent = v;
                        }
                    }

                    function openConfirm(opts) {
                        opts = opts || {};
                        var tone = opts.tone || (opts.dangerous === false ? 'primary' : 'warning');
                        if (confirmTitle) {
                            confirmTitle.textContent = opts.title || '{{ __('Aktion bestätigen') }}';
                        }
                        if (confirmMessage) {
                            confirmMessage.textContent = opts.message || '{{ __('Möchtest du diese Aktion wirklich ausführen?') }}';
                        }
                        if (confirmIcon) {
                            renderIcon(confirmIcon, opts.icon || (opts.dangerous === false ? 'help' : 'warning'));
                        }
                        applyTone(confirmHeader, confirmIconWrap, tone);
                        confirmSubmit.textContent = opts.label || '{{ __('Ausführen') }}';
                        confirmSubmit.classList.remove('btn-error', 'btn-primary', 'btn-warning', 'btn-success', 'btn-info');
                        var btnTone = opts.dangerous === false ? 'btn-primary' : (tone === 'primary' ? 'btn-primary' : (tone === 'success' ? 'btn-success' : (tone === 'info' ? 'btn-info' : 'btn-error')));
                        confirmSubmit.classList.add(btnTone);
                        if (typeof confirmDialog.showModal === 'function') {
                            confirmDialog.showModal();
                        }
                    }

                    function openNotify(opts) {
                        opts = opts || {};
                        var tone = opts.tone || 'info';
                        var defaultIcons = { info: 'info', success: 'check_circle', warning: 'warning', error: 'cancel', primary: 'info', ghost: 'info' };
                        var defaultTitles = {
                            info:    '{{ __('Hinweis') }}',
                            success: '{{ __('Erfolg') }}',
                            warning: '{{ __('Achtung') }}',
                            error:   '{{ __('Fehler') }}'
                        };
                        if (notifyTitle) {
                            notifyTitle.textContent = opts.title || defaultTitles[tone] || '{{ __('Hinweis') }}';
                        }
                        if (notifyMessage) {
                            notifyMessage.textContent = opts.message || '';
                        }
                        if (notifyIcon) {
                            renderIcon(notifyIcon, opts.icon || defaultIcons[tone] || 'info');
                        }
                        applyTone(notifyHeader, notifyIconWrap, tone);
                        if (notifyOk) {
                            notifyOk.classList.remove('btn-error', 'btn-primary', 'btn-warning', 'btn-success', 'btn-info');
                            var okTone = tone === 'error' ? 'btn-error' : (tone === 'warning' ? 'btn-warning' : (tone === 'success' ? 'btn-success' : 'btn-primary'));
                            notifyOk.classList.add(okTone);
                        }
                        if (typeof notifyDialog.showModal === 'function') {
                            notifyDialog.showModal();
                        }
                    }

                    // Programmatic API: returns Promise<boolean>
                    window.confirmAction = function (opts) {
                        if (!confirmDialog) {
                            return Promise.resolve(false);
                        }
                        return new Promise(function (resolve) {
                            pendingForm = null;
                            pendingSubmitter = null;
                            pendingResolve = resolve;
                            openConfirm(typeof opts === 'string' ? { message: opts } : opts);
                        });
                    };

                    // Programmatic API: returns Promise<void>
                    window.notifyAction = function (opts) {
                        if (!notifyDialog) {
                            try { window.alert((opts && opts.message) || String(opts || '')); } catch (e) { /* ignore */ }
                            return Promise.resolve();
                        }
                        return new Promise(function (resolve) {
                            pendingNotifyResolve = resolve;
                            openNotify(typeof opts === 'string' ? { message: opts } : opts);
                        });
                    };

                    if (notifyDialog) {
                        notifyDialog.addEventListener('close', function () {
                            var r = pendingNotifyResolve;
                            pendingNotifyResolve = null;
                            if (r) r();
                        });
                    }

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
                            pendingSubmitter = null;
                            pendingResolve = null;

                            openConfirm({
                                title:   form.getAttribute('data-confirm-title') || undefined,
                                message: form.getAttribute('data-confirm-message') || undefined,
                                label:   form.getAttribute('data-confirm-label') || undefined,
                                icon:    form.getAttribute('data-confirm-icon') || undefined,
                                tone:    form.getAttribute('data-confirm-tone') || undefined,
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
                            pendingSubmitter = null;

                            if (trigger.tagName === 'BUTTON' && (trigger.type === 'submit' || !trigger.type) && ownerForm) {
                                pendingForm = ownerForm;
                                pendingSubmitter = trigger;
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
                                icon:    trigger.getAttribute('data-confirm-icon') || undefined,
                                tone:    trigger.getAttribute('data-confirm-tone') || undefined,
                            });
                        });

                        confirmSubmit.addEventListener('click', function () {
                            var formToSubmit = pendingForm;
                            var submitter = pendingSubmitter;
                            var resolver = pendingResolve;
                            pendingForm = null;
                            pendingSubmitter = null;
                            pendingResolve = null;
                            confirmDialog.close();
                            if (formToSubmit) {
                                if (submitter && typeof formToSubmit.requestSubmit === 'function') {
                                    formToSubmit.requestSubmit(submitter);
                                } else {
                                    formToSubmit.submit();
                                }
                            }
                            if (resolver) {
                                resolver(true);
                            }
                        });

                        confirmDialog.addEventListener('close', function () {
                            var resolver = pendingResolve;
                            pendingForm = null;
                            pendingSubmitter = null;
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

                    // Persistenz pro Sektion (<details data-sidebar-section-key="…">)
                    (function () {
                        var STORAGE_KEY = 'workDiarySidebarSections';
                        var store = {};
                        try {
                            var raw = localStorage.getItem(STORAGE_KEY);
                            if (raw) { store = JSON.parse(raw) || {}; }
                        } catch (e) { store = {}; }
                        var sections = document.querySelectorAll('#app-sidebar details[data-sidebar-section-key]');
                        sections.forEach(function (details) {
                            var key = details.getAttribute('data-sidebar-section-key');
                            if (key && Object.prototype.hasOwnProperty.call(store, key)) {
                                details.open = store[key] === 1 || store[key] === '1' || store[key] === true;
                            }
                            details.addEventListener('toggle', function () {
                                store[key] = details.open ? 1 : 0;
                                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(store)); } catch (e) { /* ignore */ }
                            });
                        });
                    })();

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

            {{-- In-App-Hilfe-Drawer (MVP-051): einmal pro Seite, befüllt von JS. --}}
            @auth
                <x-help-drawer />
            @endauth
    </body>
</html>
