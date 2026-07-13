<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
        {{-- Theme-Seed (User-/Org-Auflösung) + Custom-Theme-Definitionen der
             Organisation. Beides MUSS vor dem Anti-Flash-Skript und vor dem
             CSS-Bundle stehen, damit der erste Paint das richtige (auch ein
             org-eigenes) Theme trägt. Die Farbwerte sind durch ThemeDefinition
             sanitisiert (nur HEX/erlaubte Tokens) → kein CSS-Injection-Risiko. --}}
        @if (isset($theme) && $theme)
            <script @cspNonce>window.__theme = @json($theme->seed());</script>
            @php $__customThemesCss = $theme->customThemesCss(); @endphp
            @if ($__customThemesCss !== '')
                <style @cspNonce>{!! $__customThemesCss !!}</style>
            @endif
        @endif
        <script @cspNonce>
            (function () {
                var root = document.documentElement;
                var seed = window.__theme || {};
                var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
                var choice;
                if (seed.authenticated) {
                    // Eingeloggt: ALLEIN die serverseitig aufgelöste Wahl zählt
                    // (persönliches Theme aus der DB, sonst 'auto' → Org-Hell/Dunkel-
                    // Paar). KEIN localStorage-Override — so kann kein veralteter
                    // lokaler Zustand den Org-Standard überstimmen. Der Header-
                    // Umschalter persistiert seine Wahl serverseitig
                    // (account.theme.update) und kommt damit beim nächsten Render
                    // bereits über seed.active zurück.
                    choice = seed.active || 'auto';
                } else {
                    // Gast (z. B. Login): localStorage führt, gegen die Allowlist
                    // geprüft, sonst Server-Default bzw. 'auto'.
                    var saved = null;
                    try { saved = localStorage.getItem('workDiaryTheme'); } catch (e) {}
                    if (saved && seed.allowed && seed.allowed.indexOf(saved) === -1) saved = null;
                    choice = saved || seed.active || 'auto';
                }
                var theme = choice;
                if (choice === 'auto') {
                    theme = prefersLight ? (seed.autoLight || 'corporate') : (seed.autoDark || 'dim');
                }
                var scheme = (seed.schemes && seed.schemes[theme])
                    || (theme === 'dim' || theme === 'dark' || theme === 'business' ? 'dark' : 'light');
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = scheme;

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
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}?v={{ @filemtime(public_path('manifest.webmanifest')) ?: '1' }}">
        <meta name="theme-color" content="#1d232a" media="(prefers-color-scheme: dark)">
        <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="workDiary">
        <meta name="application-name" content="workDiary">

        {{-- Material Symbols werden lokal über @fontsource im App-Bundle (resources/css/app.css)
             ausgeliefert; kein externer Fallback nötig. --}}

        <script @cspNonce>window.__translations = @json($jsTranslations ?? []);</script>
        {{-- Aktive Anzeigeformate (User → Org → config), damit der Datepicker
             (flatpickr altFormat) dasselbe Format wie die serverseitige Anzeige nutzt. --}}
        <script @cspNonce>window.__formats = @json(['date' => \App\Support\Formats::date(), 'time' => \App\Support\Formats::time()]);</script>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            {{-- Ohne Build kein gebündeltes app.css → strukturelles Layout-CSS
                 (Quelle: resources/css/layout.css) inline als Fallback. --}}
            <style>{!! file_get_contents(resource_path('css/layout.css')) !!}</style>
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
        // Kontext-Hilfe (Feature 039): Topic der aktuellen Route serverseitig
        // auflösen — nur wenn das Topic existiert und für den Nutzer sichtbar
        // ist (audience-Filter), sonst kein Kontext (Fallback im Drawer-JS).
        $_helpContextTopic = null;
        if (Auth::check()) {
            try {
                $_helpContextTopic = app(\App\Services\Help\HelpContextResolver::class)
                    ->visibleTopicFor(request(), Auth::user());
            } catch (\Throwable) {
                $_helpContextTopic = null;
            }
        }
    @endphp
    <body class="min-h-screen text-base-content {{ $_bodyMode === 'legacy' ? 'bg-base-200' : 'bg-linear-to-b from-base-200 to-base-300' }}" data-mode="{{ $_bodyMode }}"@if ($_helpContextTopic) data-help-context="{{ $_helpContextTopic }}"@endif>
        {{-- Barrierefreiheit (WCAG 2.4.1): Sprunglink zum Hauptinhalt. Visuell
             ausgeblendet (sr-only), wird beim Tab-Fokus sichtbar und springt an
             das <main id="main-content"> — Tastaturnutzer überspringen so die
             Kopf-/Navigationsleiste. MUSS das erste fokussierbare Element sein. --}}
        <a href="#main-content"
           class="wd-skip-link sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-100 focus:rounded-box focus:bg-primary focus:px-4 focus:py-2 focus:font-semibold focus:text-primary-content focus:shadow-lg focus:ring-2 focus:ring-primary/60">
            {{ __('Zum Inhalt springen') }}
        </a>
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
            // Echter Plattform-Betreiber (darf Org-Kontext wechseln + Mandanten
            // verwalten). NUR diese Kennung schaltet Cross-Tenant-Oberflächen
            // frei; ein org-lokaler Admin ($_isGlobalAdmin, Fehlname) bleibt
            // auf seine Organisation beschränkt.
            $_isPlatformAdmin = $_authUser instanceof \App\Models\User && $_authUser->isGlobalAdmin();
            // Nur AKTIVE Organisationen im Header-Switcher anbieten; deaktivierte
            // dürfen nicht als Kontext gewählt werden, bis sie über die Verwaltung
            // wieder aktiviert wurden.
            $_orgList = $_isPlatformAdmin
                ? \App\Models\Organization::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect();
            $_activeOrg = app()->bound('currentOrganization') ? app('currentOrganization') : null;
            $_activeOrgId = $_activeOrg ? (int) $_activeOrg->id : null;
            $showOrgSwitch = $_isPlatformAdmin && $_orgList->count() > 1;
        @endphp

        <header id="app-header" class="sticky top-0 z-50 bg-base-100 border-b border-base-300 shadow-xs">
            @php
                $_hasCenter = \Illuminate\Support\Facades\View::hasSection('nav-center');
                $_showCenteredRange = ! $_hasCenter && auth()->check() && ! $isLegacyMode;
                $_useGrid = $_hasCenter || $_showCenteredRange;
            @endphp
            <div class="header-row w-full px-3 xl:px-4 2xl:px-4 min-h-14 py-2">
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
                                    ['route' => 'chat.index',               'label' => __('Chat'),           'icon' => 'forum',            'modal' => false, 'matches' => ['chat.*']],
                                    ['route' => 'duty-plans.index',         'label' => __('Dienstpläne'),    'icon' => 'event_available',  'modal' => false, 'matches' => ['duty-plans.*']],
                                    ['route' => 'schedule.index',           'label' => __('Schichtplan'),    'icon' => 'schedule',         'modal' => false, 'matches' => ['schedule.*']],
                                    ['route' => 'timesheets.index',         'label' => __('Stundenzettel'),  'icon' => 'description',      'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                                    ['route' => 'customers.index',          'label' => __('Kunden'),         'icon' => 'badge',            'modal' => false, 'matches' => ['customers.*']],
                                    ['route' => 'customer-queries.index',   'label' => __('customer-query.title'), 'icon' => 'contact_support', 'modal' => false, 'matches' => ['customer-queries.*']],
                                    ['route' => 'suppliers.index',          'label' => __('Lieferanten'),    'icon' => 'local_shipping',   'modal' => false, 'matches' => ['suppliers.*']],
                                    ['route' => 'articles.index',           'label' => __('article.title'),  'icon' => 'inventory_2',      'modal' => false, 'matches' => ['articles.*']],
                                    ['route' => 'warehouses.index',         'label' => __('inventory.title'),'icon' => 'warehouse',        'modal' => false, 'matches' => ['warehouses.*', 'inventory.*']],
                                    ['route' => 'manufacturing-orders.index','label' => __('manufacturing.order.title'), 'icon' => 'precision_manufacturing', 'modal' => false, 'matches' => ['manufacturing-orders.*']],
                                    ['route' => 'serials.index',            'label' => __('inventory.serial.title'), 'icon' => 'tag', 'modal' => false, 'matches' => ['serials.*']],
                                    ['route' => 'purchase-orders.index',    'label' => __('procurement.title'), 'icon' => 'shopping_cart', 'modal' => false, 'matches' => ['purchase-orders.*']],
                                    ['route' => 'supplier-catalogs.index',  'label' => __('procurement.catalog.title'), 'icon' => 'import_export', 'modal' => false, 'matches' => ['supplier-catalogs.*']],
                                    ['route' => 'pricing-margin-rules.index','label' => __('procurement.margin.title'), 'icon' => 'percent', 'modal' => false, 'matches' => ['pricing-margin-rules.*']],
                                    ['route' => 'bill-of-quantities.index', 'label' => __('gaeb.title'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['bill-of-quantities.*']],
                                    ['route' => 'inventory.scan',           'label' => __('inventory.scan.title'), 'icon' => 'qr_code_scanner', 'modal' => false, 'matches' => ['inventory.scan*']],
                                    ['route' => 'work-centers.index',       'label' => __('manufacturing.capacity.title'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['work-centers.*']],
                                    ['route' => 'inventory.lots',           'label' => __('inventory.lot.title'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['inventory.lots*']],
                                    ['route' => 'inventory.label-templates.index', 'label' => __('inventory.label_template.title'), 'icon' => 'label', 'modal' => false, 'matches' => ['inventory.label-templates.*']],
                                    ['route' => 'projects.index',           'label' => __('Projekte'),       'icon' => 'folder_special',   'modal' => false, 'matches' => ['projects.*']],
                                    ['route' => 'invoices.index',           'label' => __('Rechnungen & Belege'), 'icon' => 'receipt_long',     'modal' => false, 'matches' => ['invoices.*', 'lexoffice.vouchers.*']],
                                    ['route' => 'finance.transfers.index',  'label' => __('finance.title.menu'), 'icon' => 'outbox',           'modal' => false, 'matches' => ['finance.transfers.*']],
                                    ['route' => 'finance.reconciliation.index', 'label' => __('bank.title.menu'), 'icon' => 'account_balance', 'modal' => false, 'matches' => ['finance.reconciliation.*', 'finance.bank-accounts.*']],
                                    ['route' => 'lexoffice.articles.index', 'label' => __('Produkte & Leistungen'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['lexoffice.articles.*']],
                                    ['route' => 'events.index',             'label' => __('Veranstaltungen'),'icon' => 'event',            'modal' => false, 'matches' => ['events.*']],
                                    ['route' => 'flex.index',               'label' => __('Arbeitszeitkonto'),'icon' => 'hourglass_top',   'modal' => false, 'matches' => ['flex.*']],
                                    ['route' => 'archive.index',            'label' => __('Archiv'),         'icon' => 'inventory_2',      'modal' => false, 'matches' => ['archive.*']],
                                ];

                            $manageNavItems = [];
                            $adminNavItems  = [];
                            $pluginPanelRoutes = []; // Routen aktiver Plugin-Panels (für Ungruppiert-Ausschluss)
                            $pluginPanelItems  = []; // fertige Menü-Items der Plugin-Panels (eigene Systemmenü-Gruppe „Plugins")
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
                                    $manageNavItems[] = ['route' => 'event-categories.index',        'label' => __('Veranstaltungs-Kategorien'), 'icon' => 'category', 'modal' => false];
                                    $manageNavItems[] = ['route' => 'shift-types.index',             'label' => __('Schichttypen'),     'icon' => 'work_history',     'modal' => false];
                                    $manageNavItems[] = ['route' => 'materials.index',               'label' => __('Materialien'),      'icon' => 'inventory',        'modal' => false];
                                    $manageNavItems[] = ['route' => 'tags.index',                    'label' => __('Tags'),             'icon' => 'label',            'modal' => false];
                                    if ($_isPlatformAdmin) {
                                        // Mandantenliste (Cross-Tenant) nur für Plattform-Betreiber.
                                        $adminNavItems[]  = ['route' => 'admin.organizations.index',     'label' => __('Organisationen'),   'icon' => 'corporate_fare',   'modal' => false];
                                    } elseif ($_authUser?->organization_id !== null) {
                                        // Org-lokaler Admin: direkter Einstieg in die EIGENE Org.
                                        $adminNavItems[]  = ['route' => 'admin.organizations.edit', 'route_params' => [$_authUser->organization_id], 'label' => __('Organisation'), 'icon' => 'corporate_fare', 'modal' => false];
                                    }
                                    $adminNavItems[]  = ['route' => 'admin.branding.edit',           'label' => __('Branding'),         'icon' => 'palette',          'modal' => false];
                                    // Theme-Editor nur bei aktivem module.theming (Pro+) einblenden.
                                    if (app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.theming')) {
                                        $adminNavItems[] = ['route' => 'admin.themes.index',          'label' => __('Themes'),           'icon' => 'format_paint',     'modal' => false, 'matches' => ['admin.themes.*']];
                                    }
                                    $adminNavItems[]  = ['route' => 'admin.entry-types.index',        'label' => __('Eintragstypen'),    'icon' => 'category',         'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.classifications.index',    'label' => __('Klassifikationen'), 'icon' => 'category_search',  'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.classification-requirements.index', 'label' => __('Pflichtregeln'), 'icon' => 'rule_settings', 'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.branch-profiles.index',    'label' => __('Branchenprofile'),  'icon' => 'storefront',       'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.expense-categories.index',  'label' => __('Spesenkategorien'), 'icon' => 'receipt_long',     'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.per-diem-rates.index',      'label' => __('Verpflegungspauschalen'), 'icon' => 'restaurant_menu',  'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.automations.index',         'label' => __('Automatisierungen'), 'icon' => 'bolt',             'modal' => false];
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::NotificationRuleViewAny->value)) {
                                        $adminNavItems[] = ['route' => 'admin.notification-rules.index', 'label' => __('notification.title.rules'), 'icon' => 'notifications_active', 'modal' => false];
                                    }
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::WebhookViewAny->value)) {
                                        $adminNavItems[] = ['route' => 'admin.webhooks.index', 'label' => __('integration.webhook.title.index'), 'icon' => 'webhook', 'modal' => false, 'matches' => ['admin.webhooks.*']];
                                    }
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::SurchargeRuleViewAny->value)) {
                                        $adminNavItems[] = ['route' => 'admin.surcharge-rules.index', 'label' => __('surcharge.title.rules'), 'icon' => 'percent', 'modal' => false];
                                    }
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::CostCenterRuleViewAny->value)) {
                                        $adminNavItems[] = ['route' => 'admin.cost-center-rules.index', 'label' => __('costcenter.title.rules'), 'icon' => 'account_balance', 'modal' => false];
                                    }
                                    // Lohnarten-Mapping + Export-Lieferung (A21 · MVP-019).
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::WageTypeMappingViewAny->value)) {
                                        $adminNavItems[] = ['route' => 'admin.wage-type-mappings.index', 'label' => __('wage_types.title.index'), 'icon' => 'badge', 'modal' => false];
                                    }
                                    // Feature 002: Zielwerte & Benchmarks pflegen (GF/Admin).
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::ReportTargetManage->value)) {
                                        $adminNavItems[] = ['route' => 'admin.report-targets.index', 'label' => __('reporting.target.nav'), 'icon' => 'flag', 'modal' => false];
                                    }
                                    // Eigene Bankkonten (Feature 045): Verwaltung über finance.config,
                                    // Plan-Gating module.finance — adminNavItems laufen nicht durch $nav->allows.
                                    if (
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::FinanceConfig->value)
                                        && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.finance')
                                    ) {
                                        $adminNavItems[] = ['route' => 'finance.bank-accounts.index', 'label' => __('bank.title.accounts'), 'icon' => 'account_balance', 'modal' => false];
                                    }
                                    // Formularvorlagen (Feature 032): Verwaltung wie surcharge-rules;
                                    // adminNavItems laufen nicht durch $nav->allows → Plan-Gating hier explizit.
                                    if (
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::FormTemplateViewAny->value)
                                        && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.forms')
                                    ) {
                                        $adminNavItems[] = ['route' => 'form-templates.index', 'label' => __('form.title.templates'), 'icon' => 'assignment', 'modal' => false];
                                    }
                                    // Prozedurvorlagen-Designer (Feature 026): Verwaltung wie Formularvorlagen.
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::ProcedureTemplateView->value)) {
                                        $adminNavItems[] = ['route' => 'procedures.index', 'label' => __('procedure.title.templates'), 'icon' => 'rule', 'modal' => false, 'matches' => ['procedures.*']];
                                    }
                                    $adminNavItems[]  = ['route' => 'admin.data.index',                'label' => __('Datentransfer'),    'icon' => 'sync_alt',         'modal' => false];
                                    if (($_authUser?->canManageBilling() ?? false) && \Illuminate\Support\Facades\Route::has('admin.integration.inbox')) {
                                        $_iiOrg = $_authUser?->organization_id;
                                        $_iiOpen = $_iiOrg !== null
                                            ? \App\Models\IntegrationInboxItem::query()
                                                ->where('organization_id', $_iiOrg)
                                                ->where('status', \App\Models\IntegrationInboxItem::STATUS_OPEN)
                                                ->count()
                                            : 0;
                                        $adminNavItems[] = ['route' => 'admin.integration.inbox', 'label' => __('Zuordnungs-Inbox'), 'icon' => 'rule', 'modal' => false, 'matches' => ['admin.integration.*'], 'badge' => $_iiOpen];
                                    }
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
                                    if (\Illuminate\Support\Facades\Gate::allows('platform.license.view')) {
                                        $adminNavItems[] = ['route' => 'admin.license.index',            'label' => __('Lizenz'),           'icon' => 'key',              'modal' => false];
                                    }
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::MetricsView->value)) {
                                        $adminNavItems[] = ['route' => 'admin.metrics.index',            'label' => __('metrics.title.index'), 'icon' => 'monitoring',    'modal' => false];
                                        // Komponenten- und Versionsübersicht inkl. Release-SBOM (Feature 044) — gleiche Admin-Schutzstufe.
                                        $adminNavItems[] = ['route' => 'admin.components.index',         'label' => __('isms.components.title'), 'icon' => 'receipt_long', 'modal' => false];
                                    }
                                    // Admin-Sicherheitsübersicht (Feature 016) — eigene Schutzstufe security.view.
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::SecurityView->value)) {
                                        $adminNavItems[] = ['route' => 'admin.security.index',           'label' => __('security.title.index'), 'icon' => 'shield_lock', 'modal' => false];
                                    }
                                    // Backup- & Restore-Status (Feature 017) — plattformweite Admin-Sicht.
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::BackupView->value)) {
                                        $adminNavItems[] = ['route' => 'admin.backup.status',            'label' => __('backup.title.status'), 'icon' => 'backup',        'modal' => false];
                                    }
                                    // Scheduler-Steuerung (Feature 067, MVP-176).
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::PlatformSchedulerManage->value)) {
                                        $adminNavItems[] = ['route' => 'admin.scheduler.index',          'label' => __('scheduler.title.index'), 'icon' => 'schedule',    'modal' => false];
                                    }
                                    // Einstellungs-Registry (Feature 067, MVP-174).
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::PlatformSettingsManage->value)) {
                                        $adminNavItems[] = ['route' => 'admin.settings.index',           'label' => __('settingsregistry.title.index'), 'icon' => 'tune',      'modal' => false];
                                    }
                                    // Wartungsfenster (Feature 022/041, MVP-055).
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::PlatformOperationsManage->value)) {
                                        $adminNavItems[] = ['route' => 'admin.maintenance-windows.index', 'label' => __('maintenance.window.title'), 'icon' => 'engineering', 'modal' => false];
                                    }
                                    // Admin-Aufgabencenter (Feature 041, MVP-058). Badge = aktive Aufgaben
                                    // der Org (B3/MVP-344): gecachter Count (kein Query pro Request);
                                    // Invalidierung via OperationsTask::booted bei jeder Schreiboperation.
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::PlatformOperationsView->value)) {
                                        $_opsOrg = $_authUser?->organization_id;
                                        $_opsOpen = $_opsOrg !== null
                                            ? (int) \Illuminate\Support\Facades\Cache::remember(
                                                \App\Models\OperationsTask::navBadgeCacheKey((int) $_opsOrg),
                                                \App\Models\OperationsTask::NAV_BADGE_TTL,
                                                static fn(): int => \App\Models\OperationsTask::query()
                                                    ->where('organization_id', $_opsOrg)
                                                    ->active()
                                                    ->count(),
                                            )
                                            : 0;
                                        $adminNavItems[] = ['route' => 'admin.operations.index',        'label' => __('operations.title.index'), 'icon' => 'task_alt', 'modal' => false, 'badge' => $_opsOpen];
                                    }
                                    // Fehlermeldungs-Inbox (Feature 041, MVP-053).
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::ProblemReportManage->value)) {
                                        $adminNavItems[] = ['route' => 'admin.problem-reports.index',    'label' => __('problemreport.title.inbox'), 'icon' => 'flag',    'modal' => false];
                                    }
                                    // Temporäre Supportfreigaben (Rang 64) — Kundenadmin-Sicht.
                                    if (\Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::SupportGrantManage->value)) {
                                        $adminNavItems[] = ['route' => 'admin.support.grants.index',     'label' => __('Supportfreigaben'), 'icon' => 'support_agent',    'modal' => false];
                                    }
                                    if (\Illuminate\Support\Facades\Gate::allows('whistleblowing.settings.manage')) {
                                        $adminNavItems[] = ['route' => 'whistleblowing.portal.edit',     'label' => __('Meldeportal'),      'icon' => 'campaign',         'modal' => false];
                                    }
                                    $adminNavItems[] = ['route' => 'admin.plugins.index',                'label' => __('Plugins'),          'icon' => 'extension',        'modal' => false];
                                    $adminNavItems[] = ['route' => 'admin.plugin-errors.index',          'label' => __('Plugin-Fehler'),    'icon' => 'bug_report',       'modal' => false];

                                    // Aktive Plugins mit eigenem Admin-Panel (adminPanel()) dynamisch
                                    // ins Systemmenü aufnehmen — gruppiert unter „Plugins" (siehe $adminGroups).
                                    foreach (app(\App\Plugins\PluginManager::class)->enabled() as $_plugin) {
                                        $_panel = $_plugin->adminPanel();
                                        if ($_panel === null || empty($_panel['route'])) {
                                            continue;
                                        }
                                        $_routeDef = \Illuminate\Support\Facades\Route::getRoutes()->getByName($_panel['route']);
                                        if ($_routeDef === null) {
                                            continue; // Route (noch) nicht registriert – Plugin liefert sie ggf. erst bei Aktivierung
                                        }
                                        // Manche Plugins zeigen auf admin.plugins.edit/{plugin}; benötigte
                                        // Parameter mit der Plugin-ID auffüllen, sonst wirft route() beim Rendern.
                                        $_params = count($_routeDef->parameterNames()) > 0 ? [$_plugin->id()] : [];
                                        $_item = [
                                            'route'        => $_panel['route'],
                                            'route_params' => $_params,
                                            'label'        => $_panel['label'] ?? $_plugin->name(),
                                            'icon'         => $_panel['icon'] ?? 'extension',
                                            // admin.plugins.edit rendert nur das Settings-Modal-Fragment → als Modal-Trigger öffnen.
                                            'modal'        => $_panel['route'] === 'admin.plugins.edit',
                                        ];
                                        $adminNavItems[]    = $_item;
                                        $pluginPanelItems[] = $_item;
                                        $pluginPanelRoutes[] = $_panel['route'];
                                    }
                                }
                                $adminNavItems[] = ['route' => 'admin.legacy-migration.index',      'label' => __('Legacy-Migration'), 'icon' => 'sync_alt',         'modal' => false];
                            }
                            if (! $isLegacyMode && (\Illuminate\Support\Facades\Gate::allows('manage-members') || $_authUser?->can(\App\Enums\User\Permission::UserPayrollManage->value))) {
                                // Admin (volle Verwaltung) ODER Personalverwaltung/Geschäftsführung
                                // (Personal-/Lohndaten + Arbeitszeit-Modell) erreichen den Bereich.
                                $manageNavItems[] = ['route' => 'org.members.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
                            } elseif (! $isLegacyMode && $_isPlatformAdmin) {
                                // Plattform-Betreiber ohne Org-Kontext: Eintrag verlinkt auf die
                                // Mandanten-Verwaltung, damit er sich (oder eine Organisation)
                                // zuordnen kann, bevor Mitglieder gepflegt werden.
                                $manageNavItems[] = ['route' => 'admin.organizations.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
                            }
                            if (! $isLegacyMode && $_authUser?->can(\App\Enums\User\Permission::TeamViewAny->value)) {
                                $manageNavItems[] = ['route' => 'teams.index', 'label' => __('Teams'), 'icon' => 'groups', 'modal' => false];
                            }
                            if (! $isLegacyMode && $_authUser?->can(\App\Enums\User\Permission::UserPayrollManage->value)) {
                                $manageNavItems[] = ['route' => 'payroll.index', 'label' => __('Lohn & SV'), 'icon' => 'payments', 'modal' => false];
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
                            $userNavItems[] = ['route' => 'account.2fa.show', 'label' => __('Zwei-Faktor-Authentifizierung'), 'modal' => false];
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
                                    'groups'      => [
                                        [
                                            'key'   => 'work-capture',
                                            'label' => __('Erfassung'),
                                            'icon'  => 'edit_note',
                                            'items' => array_values(array_filter([
                                                // „Heute" ist seit der Zusammenlegung auch die Tagesabschluss-Seite
                                                // (MVP-015) für den eigenen Tag; daher matcht der Eintrag auch day-close.*
                                                // (die day-close.*-Route bleibt für Fremdtage/Admin via ?user= erhalten).
                                                ['route' => 'today.show',      'label' => __('Heute'),         'icon' => 'today',             'modal' => false, 'matches' => ['today.show', 'day-close.*']],
                                                ['route' => $indexRoute,       'label' => __('Arbeitsliste'),  'icon' => 'list_alt',          'modal' => false, 'matches' => [$indexRoute, 'diary.*']],
                                                ['route' => 'week.index',      'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week','modal' => false, 'matches' => ['week.index']],
                                                ['route' => 'kanban.index',    'label' => __('Kanban'),        'icon' => 'view_kanban',       'modal' => false, 'matches' => ['kanban.index']],
                                                // Agiles Projektmanagement (Feature 064, B3/MVP-344): Einstieg über die
                                                // org-weite Management-Übersicht (P10) — Board/Backlog sind projekt-
                                                // gebunden und dort verlinkt. Recht wie die Route (agile.report.view),
                                                // Modul-Gating via $moduleByItemRoute (module.agile_projects).
                                                \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::AgileReportView->value)
                                                    ? ['route' => 'agile.reports.overview', 'label' => __('Agile Übersicht'), 'icon' => 'sprint', 'modal' => false, 'matches' => ['agile.*']]
                                                    : null,
                                                ['route' => 'attendance.index','label' => __('Stempeluhr'),    'icon' => 'punch_clock',       'modal' => false, 'matches' => ['attendance.*']],
                                            ])),
                                        ],
                                        [
                                            'key'   => 'work-knowledge',
                                            'label' => __('Wissen & Doku'),
                                            'icon'  => 'menu_book',
                                            'items' => array_values(array_filter([
                                                // Dokumente & Formulare (MVP-031/032) sind auf der Seite per Tab-Leiste
                                                // (documents/_tabs) zusammengelegt → ein Menüeintrag. Die Route zeigt auf
                                                // die jeweils zugängliche Seite (Recht UND Modul), damit der Eintrag auch
                                                // sichtbar bleibt, wenn nur eines von beiden verfügbar ist; der bestehende
                                                // Filter (Modul + mayAccess) validiert die gewählte Route.
                                                [
                                                    'route' => (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Document::class)
                                                            && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.documents'))
                                                        ? 'documents.index' : 'form-submissions.index',
                                                    'label' => __('document.title.index') . ' & ' . __('form.title.submissions'),
                                                    'icon' => 'folder_open', 'modal' => false,
                                                    'matches' => ['documents.*', 'form-submissions.*'],
                                                ],
                                                // Wissensbasis (Feature 011): Recht via NavGate (@can knowledge.viewAny
                                                // über KnowledgeArticle-Policy), Modul-Gating via $moduleByItemRoute.
                                                ['route' => 'knowledge.index', 'label' => __('knowledge.title.index'), 'icon' => 'school', 'modal' => false, 'matches' => ['knowledge.*']],
                                                // Ideenlandkarten (Feature 054): Recht via NavGate (ideas.viewAny),
                                                // Modul-Gating via $moduleByItemRoute.
                                                ['route' => 'ideas.index', 'label' => __('ideas.title.index'), 'icon' => 'emoji_objects', 'modal' => false, 'matches' => ['ideas.*']],
                                                // Sicherheitsereignisse (Arbeitsschutz, Feature 013): sichtbar
                                                // für Melder (safety.report) und Register-Berechtigte (safety.viewAny/manage).
                                                (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\SafetyEvent::class)
                                                    || \Illuminate\Support\Facades\Gate::allows('create', \App\Models\SafetyEvent::class))
                                                    ? ['route' => 'safety-events.index', 'label' => __('safety.title.index'), 'icon' => 'health_and_safety', 'modal' => false, 'matches' => ['safety-events.*']]
                                                    : null,
                                            ])),
                                        ],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'plan',
                                    'label'       => __('Planung'),
                                    'collapsible' => true,
                                    'items'       => [
                                        // Dienstpläne + Verfügbarkeit/Wunschdienste sind auf der Seite per
                                        // Tab-Leiste zusammengelegt (schedule/_duty_tabs) → ein Menüeintrag.
                                        ['route' => 'duty-plans.index', 'label' => __('Dienstpläne'),   'icon' => 'event_available', 'modal' => false, 'matches' => ['duty-plans.*', 'schedule.availability.*']],
                                        // Schichtplan + Schichttausch ebenso (schedule/_shift_tabs).
                                        ['route' => 'schedule.index',   'label' => __('Schichtplan'),   'icon' => 'schedule',        'modal' => false, 'matches' => ['schedule.index', 'schedule.api.*', 'schedule.shifts.*', 'schedule.types.*', 'schedule.import.*', 'schedule.suggest', 'schedule.exchanges.*']],
                                        ['route' => 'timesheets.index', 'label' => __('Stundenzettel'), 'icon' => 'description',     'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                                        ['route' => 'flex.index',       'label' => __('Arbeitszeitkonto'),'icon' => 'hourglass_top',  'modal' => false, 'matches' => ['flex.*']],
                                        ['route' => 'tours.index',      'label' => __('Touren'),        'icon' => 'route',           'modal' => false, 'matches' => ['tours.index', 'tours.map', 'tours.create', 'tours.show', 'tours.edit']],
                                        // Leitstelle (Feature 029): Dispatch-Board + Karte. Recht über die
                                        // Permission dispatch.viewAny (Feature 028), Modul-Gating module.planung.
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::DispatchViewAny->value)
                                            ? ['route' => 'dispatch.board', 'label' => __('Leitstelle'), 'icon' => 'dashboard', 'modal' => false, 'matches' => ['dispatch.board', 'dispatch.map']]
                                            : null,
                                    ],
                                ];
                                $sidebarSections[count($sidebarSections) - 1]['items'] = array_values(array_filter($sidebarSections[count($sidebarSections) - 1]['items']));
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
                                        ['route' => 'rooms.index',     'label' => __('Räume'),      'icon' => 'meeting_room','modal' => false, 'matches' => ['rooms.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'servicedesk',
                                    'label'       => __('Service Desk'),
                                    'collapsible' => true,
                                    'items'       => array_values(array_filter([
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::ServiceTicketView->value)
                                            ? ['route' => 'service-tickets.index', 'label' => __('Tickets'), 'icon' => 'confirmation_number', 'modal' => false, 'matches' => ['service-tickets.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::ServiceTicketView->value)
                                            ? ['route' => 'helpdesk.board.index', 'label' => __('Queue-Board'), 'icon' => 'view_kanban', 'modal' => false, 'matches' => ['helpdesk.board.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::HelpdeskQueueManage->value)
                                            ? ['route' => 'helpdesk.queues.index', 'label' => __('Queues'), 'icon' => 'inbox', 'modal' => false, 'matches' => ['helpdesk.queues.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::HelpdeskQueueManage->value)
                                            ? ['route' => 'helpdesk.routing.index', 'label' => __('Ticket-Routing'), 'icon' => 'alt_route', 'modal' => false, 'matches' => ['helpdesk.routing.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\RequestItem::class)
                                            ? ['route' => 'servicedesk.catalog.index', 'label' => __('Servicekatalog'), 'icon' => 'storefront', 'modal' => false, 'matches' => ['servicedesk.catalog.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::ServiceRequestApprove->value)
                                            ? ['route' => 'servicedesk.approvals.index', 'label' => __('Genehmigungen'), 'icon' => 'approval', 'modal' => false, 'matches' => ['servicedesk.approvals.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Problem::class)
                                            ? ['route' => 'servicedesk.problems.index', 'label' => __('Probleme'), 'icon' => 'troubleshoot', 'modal' => false, 'matches' => ['servicedesk.problems.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Change::class)
                                            ? ['route' => 'servicedesk.changes.index', 'label' => __('Changes'), 'icon' => 'published_with_changes', 'modal' => false, 'matches' => ['servicedesk.changes.*', 'servicedesk.change-templates.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::SlaContractView->value)
                                            ? ['route' => 'sla-contracts.index', 'label' => __('SLA-Verträge'), 'icon' => 'handshake', 'modal' => false, 'matches' => ['sla-contracts.*']]
                                            : null,
                                        \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::SlaViewAny->value)
                                            ? ['route' => 'helpdesk.reports.index', 'label' => __('Helpdesk-Bericht'), 'icon' => 'monitoring', 'modal' => false, 'matches' => ['helpdesk.reports.*']]
                                            : null,
                                    ])),
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'location',
                                    'label'       => __('Standorterfassung'),
                                    'collapsible' => true,
                                    'items'       => [
                                        ['route' => 'geofences.index',      'label' => __('Geofences'),          'icon' => 'pin_drop',     'modal' => false, 'matches' => ['geofences.*']],
                                        ['route' => 'location.review.index', 'label' => __('Standort-Vorschläge'), 'icon' => 'where_to_vote', 'modal' => false, 'matches' => ['location.review.*']],
                                        ['route' => 'location.devices.index', 'label' => __('Meine Geräte'),       'icon' => 'smartphone',    'modal' => false, 'matches' => ['location.devices.*']],
                                    ],
                                ];
                                $sidebarSections[] = [
                                    'key'         => 'sales',
                                    'label'       => __('Vertrieb & Abrechnung'),
                                    'collapsible' => true,
                                    'groups'      => [
                                        [
                                            'key'   => 'sales-crm',
                                            'label' => __('Vertrieb'),
                                            'icon'  => 'badge',
                                            'items' => [
                                                ['route' => 'customers.index', 'label' => __('Kunden'),          'icon' => 'badge',          'modal' => false, 'matches' => ['customers.*']],
                                                ['route' => 'suppliers.index', 'label' => __('Lieferanten'),     'icon' => 'local_shipping', 'modal' => false, 'matches' => ['suppliers.*']],
                                                ['route' => 'projects.index',  'label' => __('Projekte'),        'icon' => 'folder_special', 'modal' => false, 'matches' => ['projects.*']],
                                                ['route' => 'events.index',    'label' => __('Veranstaltungen'), 'icon' => 'event',          'modal' => false, 'matches' => ['events.*']],
                                                // Feature 068: Auftragsbewerbungen — Recht via NavGate (tender.viewAny),
                                                // Modul-Gating via $moduleByItemRoute (module.applications).
                                                ['route' => 'tenders.index',   'label' => __('Ausschreibungen'), 'icon' => 'gavel',          'modal' => false, 'matches' => ['tenders.*']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'sales-recruiting',
                                            'label' => __('Personalgewinnung'),
                                            'icon'  => 'person_search',
                                            'items' => [
                                                // Feature 068: Bewerberdaten — eigener Rechtebereich (recruiting.*).
                                                ['route' => 'recruiting.requisitions.index', 'label' => __('Stellen'), 'icon' => 'work', 'modal' => false, 'matches' => ['recruiting.requisitions.*']],
                                                ['route' => 'recruiting.applications.index', 'label' => __('Bewerbungen'), 'icon' => 'person_search', 'modal' => false, 'matches' => ['recruiting.applications.*']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'sales-inventory',
                                            'label' => __('Lager & Fertigung'),
                                            'icon'  => 'warehouse',
                                            'items' => [
                                                ['route' => 'articles.index',  'label' => __('article.title'),  'icon' => 'inventory_2',     'modal' => false, 'matches' => ['articles.*']],
                                                ['route' => 'warehouses.index','label' => __('inventory.title'),'icon' => 'warehouse',       'modal' => false, 'matches' => ['warehouses.*', 'inventory.*']],
                                                ['route' => 'manufacturing-orders.index','label' => __('manufacturing.order.title'), 'icon' => 'precision_manufacturing', 'modal' => false, 'matches' => ['manufacturing-orders.*']],
                                                ['route' => 'serials.index',   'label' => __('inventory.serial.title'), 'icon' => 'tag', 'modal' => false, 'matches' => ['serials.*']],
                                                ['route' => 'purchase-orders.index','label' => __('procurement.title'), 'icon' => 'shopping_cart', 'modal' => false, 'matches' => ['purchase-orders.*']],
                                                ['route' => 'supplier-catalogs.index','label' => __('procurement.catalog.title'), 'icon' => 'import_export', 'modal' => false, 'matches' => ['supplier-catalogs.*']],
                                                ['route' => 'pricing-margin-rules.index','label' => __('procurement.margin.title'), 'icon' => 'percent', 'modal' => false, 'matches' => ['pricing-margin-rules.*']],
                                                ['route' => 'bill-of-quantities.index','label' => __('gaeb.title'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['bill-of-quantities.*']],
                                                ['route' => 'inventory.scan',  'label' => __('inventory.scan.title'), 'icon' => 'qr_code_scanner', 'modal' => false, 'matches' => ['inventory.scan*']],
                                                ['route' => 'work-centers.index','label' => __('manufacturing.capacity.title'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['work-centers.*']],
                                                ['route' => 'inventory.lots',  'label' => __('inventory.lot.title'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['inventory.lots*']],
                                                ['route' => 'inventory.label-templates.index', 'label' => __('inventory.label_template.title'), 'icon' => 'label', 'modal' => false, 'matches' => ['inventory.label-templates.*']],
                                            ],
                                        ],
                                        [
                                            'key'   => 'sales-billing',
                                            'label' => __('Abrechnung & Finanzen'),
                                            'icon'  => 'request_quote',
                                            'items' => [
                                                ['route' => 'invoices.index',  'label' => __('Rechnungen & Belege'), 'icon' => 'request_quote',   'modal' => false, 'matches' => ['invoices.*', 'lexoffice.vouchers.*']],
                                                // Faktura-Übergabe (Feature 045): Recht via NavGate (@can finance.viewAny
                                                // über BillingTransfer-Policy), Modul-Gating via $moduleByItemRoute (module.finance).
                                                ['route' => 'finance.transfers.index', 'label' => __('finance.title.menu'), 'icon' => 'outbox', 'modal' => false, 'matches' => ['finance.transfers.*', 'finance.reconciliation.*', 'finance.bank-accounts.*']],
                                                // DATEV-Buchungsstapel (Feature 045, Priorität 2): Recht via NavGate
                                                // (@can finance.booking.export über DatevBookingBatch-Policy),
                                                // Modul-Gating via $moduleByItemRoute (module.finance).
                                                ['route' => 'finance.datev.index', 'label' => __('finance.datev.menu'), 'icon' => 'account_tree', 'modal' => false, 'matches' => ['finance.datev.*']],
                                                // GoBD-Z3-Datenträgerüberlassung (Feature 063): Recht via NavGate
                                                // (finance.gobd.* → GobdExport::viewAny = finance.gobd.export),
                                                // Modul-Gating via $moduleByItemRoute (module.finance).
                                                ['route' => 'finance.gobd.index', 'label' => __('gobd.title'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['finance.gobd.*']],
                                                ['route' => 'lexoffice.articles.index', 'label' => __('Produkte & Leistungen'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['lexoffice.articles.*']],
                                                // Feature 069: Investitionsplanung — Recht via NavGate (investment.viewAny),
                                                // Modul-Gating via $moduleByItemRoute (module.investments).
                                                ['route' => 'investments.index', 'label' => __('Investitionen'), 'icon' => 'trending_up', 'modal' => false, 'matches' => ['investments.*']],
                                            ],
                                        ],
                                    ],
                                ];
                                // Hinweisgeber/Meldestelle: nur fuer eigens Berechtigte
                                // (NICHT automatisch fuer Admins), siehe WhistleblowingCasePolicy.
                                // „Meldeportal"-Einstellungen liegen im Admin-Header-Menue.
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Whistleblowing\WhistleblowingCase::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'compliance',
                                        'label'       => __('Compliance'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'whistleblowing.internal.index', 'label' => __('Meldestelle'), 'icon' => 'report', 'modal' => false, 'matches' => ['whistleblowing.internal.*']],
                                        ],
                                    ];
                                }
                                // Feature 071: Nachhaltigkeit/ESG — eigene Rechte (sustainability.viewAny),
                                // Modul-Gating via $moduleByItemRoute (module.sustainability).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Sustainability\SustainabilityAssessment::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'sustainability',
                                        'label'       => __('Nachhaltigkeit'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'sustainability.index', 'label' => __('Nachhaltigkeit & ESG'), 'icon' => 'eco', 'modal' => false, 'matches' => ['sustainability.*']],
                                        ],
                                    ];
                                }
                                // Feature 072: Reklamation/Gewährleistung — eigene Rechte
                                // (claim.viewAny), Modul-Gating via $moduleByItemRoute (module.claims).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Claims\ClaimCase::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'claims',
                                        'label'       => __('Reklamationen'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'claims.index', 'label' => __('Reklamationsakten'), 'icon' => 'assignment_return', 'modal' => false, 'matches' => ['claims.index', 'claims.show']],
                                            ['route' => 'claims.reports.index', 'label' => __('Qualitätsbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['claims.reports.*']],
                                        ],
                                    ];
                                }
                                // Feature 073: Geräte-/Maschinenverleih — eigene Rechte
                                // (rental.viewAny), Modul-Gating via $moduleByItemRoute (module.rental).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Rental\RentalCase::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'rental',
                                        'label'       => __('Verleih'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'rental.index', 'label' => __('Verleihakten'), 'icon' => 'forklift', 'modal' => false, 'matches' => ['rental.index', 'rental.show']],
                                            ['route' => 'rental.calendar', 'label' => __('Verfügbarkeitskalender'), 'icon' => 'calendar_month', 'modal' => false, 'matches' => ['rental.calendar']],
                                            ['route' => 'rental.profiles.index', 'label' => __('Gerätepool'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['rental.profiles.*']],
                                            ['route' => 'rental.rates.index', 'label' => __('Preislisten'), 'icon' => 'price_change', 'modal' => false, 'matches' => ['rental.rates.*']],
                                            ['route' => 'rental.reports.index', 'label' => __('Verleihbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['rental.reports.*']],
                                        ],
                                    ];
                                }
                                // Feature 074: Leasing/Finanzierung — eigene Rechte
                                // (assetFinance.viewAny), Modul-Gating via $moduleByItemRoute (module.asset_finance).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\AssetFinance\AssetFinanceContract::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'asset-finance',
                                        'label'       => __('Leasing & Verträge'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'asset-finance.index', 'label' => __('Leasingakten'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['asset-finance.index', 'asset-finance.show']],
                                            ['route' => 'asset-finance.deadlines.index', 'label' => __('Fristenkalender'), 'icon' => 'event_upcoming', 'modal' => false, 'matches' => ['asset-finance.deadlines.*']],
                                            ['route' => 'asset-finance.reports.index', 'label' => __('Leasingbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['asset-finance.reports.*']],
                                        ],
                                    ];
                                }
                                // Feature 075: Prüfmittel/Eichung/Kalibrierung — eigene Rechte
                                // (assetCompliance.viewAny), Modul-Gating via $moduleByItemRoute (module.asset_compliance).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\AssetCompliance\AssetComplianceProfile::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'asset-compliance',
                                        'label'       => __('Prüfmittel'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'asset-compliance.index', 'label' => __('Prüf-Dashboard'), 'icon' => 'rule_settings', 'modal' => false, 'matches' => ['asset-compliance.index']],
                                            ['route' => 'asset-compliance.profiles.index', 'label' => __('Prüfprofile'), 'icon' => 'checklist', 'modal' => false, 'matches' => ['asset-compliance.profiles.*']],
                                            ['route' => 'asset-compliance.schedules.index', 'label' => __('Prüfkalender'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['asset-compliance.schedules.*']],
                                            ['route' => 'asset-compliance.reports.index', 'label' => __('Auditbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['asset-compliance.reports.*']],
                                        ],
                                    ];
                                }
                                // Feature 070: Krisenmanagement — eigene Rechte (crisis.viewAny),
                                // Modul-Gating via $moduleByItemRoute (module.crisis_management).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Crisis\CrisisCase::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'crisis',
                                        'label'       => __('Krisenmanagement'),
                                        'collapsible' => true,
                                        'items'       => [
                                            ['route' => 'crisis.index', 'label' => __('Krisenakten'), 'icon' => 'emergency_home', 'modal' => false, 'matches' => ['crisis.index', 'crisis.show']],
                                            ['route' => 'crisis.exercises.index', 'label' => __('Übungen'), 'icon' => 'model_training', 'modal' => false, 'matches' => ['crisis.exercises.*']],
                                        ],
                                    ];
                                }
                                // Datenschutzmanagement: nur fuer die Rolle `datenschutz`
                                // (NICHT automatisch fuer Admins); modul-gegatet (Pro+).
                                if (
                                    \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Privacy\ProcessingActivity::class)
                                    || \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Privacy\DataSubjectRequest::class)
                                ) {
                                    $sidebarSections[] = [
                                        'key'         => 'datenschutz',
                                        'label'       => __('Datenschutz'),
                                        'collapsible' => true,
                                        'groups'      => [
                                            [
                                                'key'   => 'datenschutz-records',
                                                'label' => __('Verzeichnisse'),
                                                'icon'  => 'fact_check',
                                                'items' => [
                                                    ['route' => 'dataprotection.activities.index', 'label' => __('Verarbeitungstätigkeiten'), 'icon' => 'fact_check', 'modal' => false, 'matches' => ['dataprotection.activities.*']],
                                                    ['route' => 'dataprotection.processors.index', 'label' => __('Dienstleister & AVV'), 'icon' => 'handshake', 'modal' => false, 'matches' => ['dataprotection.processors.*', 'dataprotection.agreements.*']],
                                                    ['route' => 'dataprotection.gvv.index', 'label' => __('Gemeinsame Verantwortlichkeit'), 'icon' => 'diversity_3', 'modal' => false, 'matches' => ['dataprotection.gvv.*']],
                                                    ['route' => 'dataprotection.tom.index', 'label' => __('TOM-Katalog'), 'icon' => 'shield_lock', 'modal' => false, 'matches' => ['dataprotection.tom.*']],
                                                ],
                                            ],
                                            [
                                                'key'   => 'datenschutz-cases',
                                                'label' => __('Vorfälle & Prüfung'),
                                                'icon'  => 'gpp_maybe',
                                                'items' => [
                                                    ['route' => 'dataprotection.requests.index', 'label' => __('Betroffenenanfragen'), 'icon' => 'contact_mail', 'modal' => false, 'matches' => ['dataprotection.requests.*']],
                                                    ['route' => 'dataprotection.incidents.index', 'label' => __('Datenschutzvorfälle'), 'icon' => 'gpp_maybe', 'modal' => false, 'matches' => ['dataprotection.incidents.*']],
                                                    ['route' => 'dataprotection.compliance.index', 'label' => __('Lückenanalyse'), 'icon' => 'rule', 'modal' => false, 'matches' => ['dataprotection.compliance.*']],
                                                ],
                                            ],
                                        ],
                                    ];
                                }
                                // ISMS (Feature 044): admin + geschaeftsfuehrung (isms.viewAny);
                                // modul-gegatet (NUR Enterprise, module.isms).
                                if (\Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Isms\IsmsRisk::class)) {
                                    $sidebarSections[] = [
                                        'key'         => 'isms',
                                        'label'       => __('isms.title.section'),
                                        'collapsible' => true,
                                        'groups'      => [
                                            [
                                                'key'   => 'isms-governance',
                                                'label' => __('Steuerung'),
                                                'icon'  => 'monitoring',
                                                'items' => [
                                                    // Auditbereitschaft (Feature 044, MVP 1): bewusst ERSTER Eintrag des Bereichs.
                                                    ['route' => 'isms.dashboard', 'label' => __('isms.title.dashboard'), 'icon' => 'monitoring', 'modal' => false, 'matches' => ['isms.dashboard']],
                                                    // Reifegrad-/Readiness-Assessment (Feature 044, MVP 3): begruendete Selbsteinschaetzung.
                                                    ['route' => 'isms.readiness', 'label' => __('isms.title.readiness'), 'icon' => 'speed', 'modal' => false, 'matches' => ['isms.readiness']],
                                                    ['route' => 'isms.requirements.index', 'label' => __('isms.title.requirements'), 'icon' => 'checklist', 'modal' => false, 'matches' => ['isms.requirements.*', 'isms.statements.*']],
                                                    ['route' => 'isms.csf', 'label' => __('isms.title.csf'), 'icon' => 'radar', 'modal' => false, 'matches' => ['isms.csf', 'isms.csf.*']],
                                                    ['route' => 'isms.controls.index', 'label' => __('isms.title.controls'), 'icon' => 'verified_user', 'modal' => false, 'matches' => ['isms.controls.*']],
                                                    ['route' => 'isms.risks.index', 'label' => __('isms.title.risks'), 'icon' => 'warning_amber', 'modal' => false, 'matches' => ['isms.risks.*']],
                                                ],
                                            ],
                                            [
                                                'key'   => 'isms-operations',
                                                'label' => __('Betrieb'),
                                                'icon'  => 'report',
                                                'items' => [
                                                    // Betrieb und Wirksamkeit (Feature 044, MVP 2): Vorfaelle, Schwachstellen, Advisories.
                                                    ['route' => 'isms.incidents.index', 'label' => __('isms.title.incidents'), 'icon' => 'report', 'modal' => false, 'matches' => ['isms.incidents.*']],
                                                    ['route' => 'isms.vulnerabilities.index', 'label' => __('isms.title.vulnerabilities'), 'icon' => 'bug_report', 'modal' => false, 'matches' => ['isms.vulnerabilities.*', 'isms.advisories.*']],
                                                    ['route' => 'isms.software.index', 'label' => __('isms.title.software'), 'icon' => 'apps', 'modal' => false, 'matches' => ['isms.software.*']],
                                                ],
                                            ],
                                            [
                                                'key'   => 'isms-audit',
                                                'label' => __('Lieferanten & Audit'),
                                                'icon'  => 'handshake',
                                                'items' => array_values(array_filter([
                                                    // Lieferanten und Vertraege (Feature 044, MVP 2/3): Lieferantenbewertung.
                                                    ['route' => 'isms.suppliers.index', 'label' => __('isms.title.suppliers'), 'icon' => 'handshake', 'modal' => false, 'matches' => ['isms.suppliers.*']],
                                                    ['route' => 'isms.conformity.index', 'label' => __('isms.title.conformity'), 'icon' => 'workspace_premium', 'modal' => false, 'matches' => ['isms.conformity.*']],
                                                    ['route' => 'isms.audits.index', 'label' => __('isms.title.audits'), 'icon' => 'fact_check', 'modal' => false, 'matches' => ['isms.audits.*']],
                                                    ['route' => 'isms.reviews.index', 'label' => __('isms.title.reviews'), 'icon' => 'grading', 'modal' => false, 'matches' => ['isms.reviews.*']],
                                                    ['route' => 'isms.packages.index', 'label' => __('isms.title.packages'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['isms.packages.*']],
                                                    ['route' => 'isms.soa', 'label' => __('isms.title.soa'), 'icon' => 'rule_folder', 'modal' => true, 'matches' => ['isms.soa']],
                                                    // Geltungsbereiche: Verwaltungsflaeche, nur isms.manage (IsmsScopePolicy).
                                                    \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Isms\IsmsScope::class)
                                                        ? ['route' => 'isms.scopes.index', 'label' => __('isms.title.scopes'), 'icon' => 'travel_explore', 'modal' => false, 'matches' => ['isms.scopes.*']]
                                                        : null,
                                                ])),
                                            ],
                                        ],
                                    ];
                                }
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
                                            'items' => array_values(array_filter([
                                                ['route' => 'reports.week-by-user',   'label' => __('Woche pro Mitarbeiter'), 'icon' => 'date_range',  'modal' => false, 'matches' => ['reports.week-by-user']],
                                                ['route' => 'reports.month-by-user-team', 'label' => __('Monat pro Mitarbeiter'), 'icon' => 'calendar_view_month', 'modal' => false, 'matches' => ['reports.month-by-user-team']],
                                                ['route' => 'reports.coverage',       'label' => __('Coverage'),              'icon' => 'group_work',  'modal' => false, 'matches' => ['reports.coverage']],
                                                ['route' => 'reports.absences',       'label' => __('Urlaub & Flex'),         'icon' => 'event_busy',  'modal' => false, 'matches' => ['reports.absences']],
                                                ['route' => 'reports.sickness',       'label' => __('Krankheiten'),           'icon' => 'sick',        'modal' => false, 'matches' => ['reports.sickness']],
                                                ['route' => 'reports.qualifications', 'label' => __('Qualifikationen'),       'icon' => 'verified',    'modal' => false, 'matches' => ['reports.qualifications']],
                                                // Feature 002: Kohortenvergleich vor/nach Fortbildung — org-weite Personaldaten → nur report.view/Admin.
                                                (auth()->user()?->isAdmin() || auth()->user()?->can(\App\Enums\User\Permission::ReportView->value))
                                                    ? ['route' => 'reports.cohort-comparison', 'label' => __('reporting.cohort.nav'), 'icon' => 'compare_arrows', 'modal' => false, 'matches' => ['reports.cohort-comparison']]
                                                    : null,
                                                auth()->user()?->can(\App\Enums\User\Permission::SafetyViewAny->value)
                                                    ? ['route' => 'reports.safety', 'label' => __('safety.report.nav'), 'icon' => 'health_and_safety', 'modal' => false, 'matches' => ['reports.safety']]
                                                    : null,
                                            ])),
                                        ],
                                        [
                                            'key'   => 'reports-projects',
                                            'label' => __('Projekte & Kunden'),
                                            'icon'  => 'folder_special',
                                            'items' => array_values(array_filter([
                                                ['route' => 'reports.customers',        'label' => __('Kundenanalyse'),     'icon' => 'bar_chart',  'modal' => false, 'matches' => ['reports.customers']],
                                                ['route' => 'reports.entry-types',      'label' => __('Auftragstypanalyse'), 'icon' => 'stacked_bar_chart', 'modal' => false, 'matches' => ['reports.entry-types']],
                                                ['route' => 'reports.assets',           'label' => __('Produktanalyse'),    'icon' => 'inventory_2', 'modal' => false, 'matches' => ['reports.assets']],
                                                ['route' => 'reports.customer-project', 'label' => __('Kunden & Projekte'), 'icon' => 'pie_chart',  'modal' => false, 'matches' => ['reports.customer-project']],
                                                ['route' => 'reports.project-details',  'label' => __('Projekt-Details'),   'icon' => 'analytics',  'modal' => false, 'matches' => ['reports.project-details']],
                                                ['route' => 'reports.project-inactive', 'label' => __('Inaktive Projekte'), 'icon' => 'folder_off', 'modal' => false, 'matches' => ['reports.project-inactive']],
                                                ['route' => 'reports.operations',       'label' => __('Operations'),        'icon' => 'assignment', 'modal' => false, 'matches' => ['reports.operations']],
                                                // SLA-Report (Feature 010): nur für SLA-Berechtigte.
                                                auth()->user()?->can(\App\Enums\User\Permission::SlaViewAny->value)
                                                    ? ['route' => 'reports.sla',        'label' => __('sla.report.nav'),    'icon' => 'timer', 'modal' => false, 'matches' => ['reports.sla', 'reports.sla.*']]
                                                    : null,
                                                // SLA-Verträge (Feature 010): read-only Detailseite, eigenes Recht.
                                                auth()->user()?->can(\App\Enums\User\Permission::SlaContractView->value)
                                                    ? ['route' => 'sla-contracts.index', 'label' => __('SLA-Verträge'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['sla-contracts.index', 'sla-contracts.show']]
                                                    : null,
                                            ])),
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
                                            'items' => array_values(array_filter([
                                                // Wirtschaftlichkeit/Nachkalkulation (Feature 014): org-weite Finanzdaten → nur report.view-Berechtigte.
                                                (auth()->user()?->isAdmin() || auth()->user()?->can(\App\Enums\User\Permission::ReportView->value))
                                                    ? ['route' => 'reports.economics', 'label' => __('Wirtschaftlichkeit'), 'icon' => 'trending_up', 'modal' => false, 'matches' => ['reports.economics']]
                                                    : null,
                                                ['route' => 'reports.billing',        'label' => __('Abrechnung'),      'icon' => 'request_quote', 'modal' => false, 'matches' => ['reports.billing']],
                                                ['route' => 'reports.expenses',       'label' => __('Spesen'),          'icon' => 'receipt_long',  'modal' => false, 'matches' => ['reports.expenses']],
                                                // Externe Auszahlungen: sensible Vergütungsdaten → nur für Payroll-Berechtigte.
                                                auth()->user()?->can(\App\Enums\User\Permission::UserPayrollManage->value)
                                                    ? ['route' => 'reports.external-payouts', 'label' => __('Externe Auszahlungen'), 'icon' => 'payments', 'modal' => false, 'matches' => ['reports.external-payouts']]
                                                    : null,
                                                // ArbZG-Compliance auf Ist-Arbeitszeit (Feature 006): nur für Compliance-Berechtigte.
                                                auth()->user()?->can(\App\Enums\User\Permission::ComplianceViewAny->value)
                                                    ? ['route' => 'reports.arbzg-compliance', 'label' => __('compliance.report.nav'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['reports.arbzg-compliance']]
                                                    : null,
                                                ['route' => 'reports.audit-activity', 'label' => __('Audit-Aktivität'), 'icon' => 'security',      'modal' => false, 'matches' => ['reports.audit-activity']],
                                            ])),
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

                            // Hartes Modul-Gating (Menue): Sektionen ausblenden, deren
                            // Modul der Plan/Lizenz nicht enthaelt. Das Route-Gate
                            // (EnforcePlanModules) schuetzt zusaetzlich gegen Direkt-URLs.
                            $moduleByKey = [
                                'plan' => 'module.planung',
                                'travel-expenses' => 'module.spesen',
                                'fleet' => 'module.fuhrpark',
                                'facility' => 'module.liegenschaften',
                                'location' => 'module.standorterfassung',
                                'sales' => 'module.vertrieb',
                                'compliance' => 'module.compliance',
                                'datenschutz' => 'module.datenschutz',
                                'isms' => 'module.isms',
                            ];
                            $features = app(\App\Services\Licensing\FeatureFlagResolver::class);
                            $nav = app(\App\Services\Navigation\NavGate::class);
                            $sidebarSections = array_values(array_filter(
                                $sidebarSections,
                                fn ($s) => ! isset($moduleByKey[$s['key']]) || $features->isEnabled($moduleByKey[$s['key']])
                            ));

                            // Feiner: einzelne Items / Report-Gruppen an Module haengen
                            // (Kanban, Team-Auswertungen) UND zusaetzlich nach Rechten filtern
                            // (viewAny der zugehoerigen Policy via NavGate).
                            $moduleByItemRoute = [
                                'kanban.index' => 'module.kanban',
                                'agile.reports.overview' => 'module.agile_projects',
                                'tenders.index' => 'module.applications',
                                'investments.index' => 'module.investments',
                                'crisis.index' => 'module.crisis_management',
                                'sustainability.index' => 'module.sustainability',
                                'claims.index' => 'module.claims',
                                'claims.reports.index' => 'module.claims',
                                'rental.index' => 'module.rental',
                                'rental.calendar' => 'module.rental',
                                'rental.profiles.index' => 'module.rental',
                                'rental.rates.index' => 'module.rental',
                                'rental.reports.index' => 'module.rental',
                                'asset-finance.index' => 'module.asset_finance',
                                'asset-finance.deadlines.index' => 'module.asset_finance',
                                'asset-finance.reports.index' => 'module.asset_finance',
                                'asset-compliance.index' => 'module.asset_compliance',
                                'asset-compliance.profiles.index' => 'module.asset_compliance',
                                'asset-compliance.schedules.index' => 'module.asset_compliance',
                                'asset-compliance.reports.index' => 'module.asset_compliance',
                                'crisis.exercises.index' => 'module.crisis_management',
                                'recruiting.requisitions.index' => 'module.applications',
                                'recruiting.applications.index' => 'module.applications',
                                'documents.index' => 'module.documents',
                                'knowledge.index' => 'module.knowledge',
                                'ideas.index' => 'module.ideas',
                                'form-submissions.index' => 'module.forms',
                                'finance.transfers.index' => 'module.finance',
                                'finance.reconciliation.index' => 'module.finance',
                                'finance.bank-accounts.index' => 'module.finance',
                                'finance.datev.index' => 'module.finance',
                                'finance.gobd.index' => 'module.finance',
                                // Lager & Fertigung (Untergruppe „Lager & Fertigung" in Vertrieb & Abrechnung):
                                // ohne module.lager ausblenden, statt nur per Route-Gate (423) zu sperren.
                                'articles.index' => 'module.lager',
                                'warehouses.index' => 'module.lager',
                                'manufacturing-orders.index' => 'module.lager',
                                'serials.index' => 'module.lager',
                                'purchase-orders.index' => 'module.lager',
                                'supplier-catalogs.index' => 'module.lager',
                                'pricing-margin-rules.index' => 'module.lager',
                                'inventory.scan' => 'module.lager',
                                'work-centers.index' => 'module.lager',
                                'inventory.lots' => 'module.lager',
                                'inventory.label-templates.index' => 'module.lager',
                                'bill-of-quantities.index' => 'module.bau',
                            ];
                            $moduleByGroupKey = [
                                'reports-team' => 'module.auswertungen_team',
                                'reports-projects' => 'module.auswertungen_team',
                                'reports-resources' => 'module.auswertungen_team',
                            ];
                            foreach ($sidebarSections as $__i => $__sec) {
                                if (! empty($__sec['items'])) {
                                    $sidebarSections[$__i]['items'] = array_values(array_filter(
                                        $__sec['items'],
                                        fn ($it) => (! isset($moduleByItemRoute[$it['route']]) || $features->isEnabled($moduleByItemRoute[$it['route']]))
                                            && $nav->mayAccess($it['route'])
                                    ));
                                }
                                if (! empty($__sec['groups'])) {
                                    $__groups = array_filter(
                                        $__sec['groups'],
                                        fn ($g) => ! isset($moduleByGroupKey[$g['key']]) || $features->isEnabled($moduleByGroupKey[$g['key']])
                                    );
                                    foreach ($__groups as $__gi => $__grp) {
                                        $__groups[$__gi]['items'] = array_values(array_filter(
                                            $__grp['items'] ?? [],
                                            // Wie der flache items-Pfad: zusätzlich pro-Item-Modul-Gating
                                            // (moduleByItemRoute), damit Untergruppen-Items ohne ihr Modul
                                            // (z. B. Finanzen/Lager/Dokumente) ausgeblendet werden.
                                            fn ($it) => (! isset($moduleByItemRoute[$it['route']]) || $features->isEnabled($moduleByItemRoute[$it['route']]))
                                                && $nav->mayAccess($it['route'])
                                        ));
                                    }
                                    $sidebarSections[$__i]['groups'] = array_values(array_filter($__groups, fn ($g) => ! empty($g['items'])));
                                }
                            }
                            unset($__sec);

                            // Leere Sektionen entfernen (weder Items noch Gruppen uebrig).
                            $sidebarSections = array_values(array_filter(
                                $sidebarSections,
                                fn ($s) => ! empty($s['items']) || ! empty($s['groups'])
                            ));

                            // Header-Navigation (Haupt- + Verwaltungsmenü): Plan UND Recht.
                            $mainNavItems = array_values(array_filter(
                                $mainNavItems,
                                fn ($it) => $nav->allows($it['route'] ?? null)
                            ));
                            $manageNavItems = array_values(array_filter(
                                $manageNavItems,
                                fn ($it) => $nav->allows($it['route'] ?? null)
                            ));
                        @endphp

                        @if ($isLegacyMode)
                            {{-- Legacy-Modus: klassische Inline-/Dropdown-Navigation im Header --}}
                            <nav class="hidden xl:flex items-center gap-1" aria-label="{{ __('Hauptnavigation') }}">
                                @foreach ($mainNavItems as $item)
                                    @php $active = collect($item['matches'])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                    <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
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
                                            <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
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
                                                :href="route($item['route'], $item['route_params'] ?? [])" />
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
                                                        :href="route($item['route'], $item['route_params'] ?? [])" />
                                        </div>
                                    @else
                                        <x-icon-btn :icon="$item['icon'] ?? 'tune'"
                                                    :tone="$active ? 'primary' : 'ghost'"
                                                    size="sm"
                                                    :label="$item['label']"
                                                    :href="route($item['route'], $item['route_params'] ?? [])" />
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
                                                ['label' => __('Planung'), 'icon' => 'event', 'routes' => ['holidays.index', 'shift-types.index', 'event-categories.index']],
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
                                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
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
                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
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
                                                ['label' => __('Organisation'), 'icon' => 'corporate_fare', 'routes' => ['admin.organizations.index', 'admin.organizations.edit', 'admin.branding.edit', 'admin.access.index']],
                                                ['label' => __('Stammdaten'), 'icon' => 'inventory_2', 'routes' => ['admin.entry-types.index', 'admin.classifications.index', 'admin.classification-requirements.index', 'admin.branch-profiles.index', 'admin.expense-categories.index', 'admin.per-diem-rates.index']],
                                                ['label' => __('Regeln & Prozesse'), 'icon' => 'account_tree', 'routes' => ['admin.automations.index', 'admin.notification-rules.index', 'admin.webhooks.index', 'admin.surcharge-rules.index', 'form-templates.index', 'whistleblowing.portal.edit']],
                                                ['label' => __('Daten & Schnittstellen'), 'icon' => 'sync_alt', 'routes' => ['admin.data.index', 'admin.remote-support.pending.index', 'admin.legacy-migration.index']],
                                                ['label' => __('Systembetrieb'), 'icon' => 'monitor_heart', 'routes' => ['audit.index', 'admin.license.index', 'admin.metrics.index', 'admin.components.index', 'admin.security.index', 'admin.backup.status', 'admin.scheduler.index', 'admin.problem-reports.index', 'admin.operations.index', 'admin.maintenance-windows.index', 'admin.settings.index']],
                                                ['label' => __('Plugins'), 'icon' => 'extension', 'routes' => ['admin.plugins.index', 'admin.plugin-errors.index']],
                                            ];
                                            $adminByRoute = collect($adminNavItems)->keyBy('route');
                                            $adminGrouped = collect();
                                            foreach ($adminGroups as $g) {
                                                $items = collect($g['routes'])->map(fn ($r) => $adminByRoute->get($r))->filter()->values();
                                                if ($items->isNotEmpty()) {
                                                    $adminGrouped->push(['label' => $g['label'], 'icon' => $g['icon'], 'items' => $items]);
                                                }
                                            }
                                            // Plugin-Panels an die Verwaltungsgruppe anhängen. Die Items werden
                                            // direkt verwendet, da mehrere Plugins dieselbe Route nutzen können.
                                            if (! empty($pluginPanelItems)) {
                                                $pluginGroupIndex = $adminGrouped->search(fn ($g) => $g['label'] === __('Plugins'));
                                                if ($pluginGroupIndex !== false) {
                                                    $pluginGroup = $adminGrouped->get($pluginGroupIndex);
                                                    $pluginGroup['items'] = $pluginGroup['items']->concat($pluginPanelItems)->values();
                                                    $adminGrouped->put($pluginGroupIndex, $pluginGroup);
                                                } else {
                                                    $adminGrouped->push(['label' => __('Plugins'), 'icon' => 'extension', 'items' => collect($pluginPanelItems)]);
                                                }
                                            }
                                            $groupedRoutes = $adminGrouped->flatMap(fn ($g) => $g['items']->pluck('route'))->all();
                                            $groupedRoutes = array_merge($groupedRoutes, $pluginPanelRoutes);
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
                                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                                                   @if (! empty($item['modal'])) data-entry-modal-trigger @endif
                                                                   class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
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
                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                                   @if (! empty($item['modal'])) data-entry-modal-trigger @endif
                                                   class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}">
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
                                     x-data="stopwatch('{{ $stopwatchEntry->started_at?->toIso8601String() }}')">
                                    <span class="relative flex size-2">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                                        <span class="relative inline-flex size-2 rounded-full bg-primary"></span>
                                    </span>
                                    <span class="font-['Space_Grotesk'] text-sm font-semibold tabular-nums text-primary"
                                          x-text="display">00:00:00</span>
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
                                     title="{{ __('Eingestempelt seit :time', ['time' => $attendanceCurrent->started_at?->ftime()]) }}"
                                     x-data="stopwatch('{{ $attendanceCurrent->started_at?->toIso8601String() }}')">
                                    <x-icon name="badge" class="text-[1rem] text-success" />
                                    <span class="font-['Space_Grotesk'] text-sm font-semibold tabular-nums text-success"
                                          x-text="displayShort">00:00</span>
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
                                    <x-button type="submit" tone="success" size="xs" class="gap-1" title="{{ __('Einstempeln') }}">
                                        <x-icon name="login" class="text-[1rem]" />
                                        <span class="hidden sm:inline">{{ __('Einstempeln') }}</span>
                                    </x-button>
                                </form>
                            @endif
                        @endisset

                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                            {{-- Kontext-Hilfe (Feature 039): Nur auf Mobil/Tablet (<lg)
                                 sichtbar. Ab lg übernimmt die permanente, minimierte
                                 Hilfe-Rail rechts den Zugang, daher lg:hidden. Mit
                                 Kontext-Topic öffnet er die Seitenhilfe, ohne öffnet er
                                 das Fallback-Panel mit Suche (Trigger ohne data-help-topic). --}}
                            <button type="button"
                                    class="btn btn-sm btn-ghost btn-square lg:hidden"
                                    data-help-trigger
                                    @if (! empty($_helpContextTopic)) data-help-topic="{{ $_helpContextTopic }}" @endif
                                    title="{{ ! empty($_helpContextTopic) ? __('Hilfe zu dieser Seite') : __('Hilfe') }}"
                                    aria-label="{{ ! empty($_helpContextTopic) ? __('Hilfe zu dieser Seite') : __('Hilfe') }}"
                                    aria-haspopup="dialog"
                                    aria-controls="help-drawer">
                                <x-icon name="help" class="text-base" />
                            </button>
                            @php
                                $_reminders = $reminderItems ?? [];
                                $_reminderTotal = collect($_reminders)->sum(fn($r) => is_object($r) ? $r->count : (int) ($r['count'] ?? 0));
                                $_reminderHasError = collect($_reminders)->contains(fn($r) => (is_object($r) ? $r->severity : ($r['severity'] ?? '')) === 'error');
                            @endphp
                            @if (! $isLegacyMode)
                            @php
                                $_bookmarks = $userBookmarks ?? collect();
                                $chatUnread = auth()->check() ? \App\Models\Chat\Channel::unreadTotalFor(auth()->user()) : 0;
                            @endphp
                            @if (app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.chat'))
                            <a id="chat-unread-link" href="{{ route('chat.index') }}"
                               data-unread-url="{{ route('chat.unread') }}"
                               class="btn btn-sm btn-ghost btn-square relative {{ request()->routeIs('chat.*') ? 'btn-active' : '' }}"
                               title="{{ __('Chat') }}" aria-label="{{ __('Chat') }}">
                                <x-icon name="forum" class="text-base" />
                                <span id="chat-unread-badge"
                                      class="badge badge-primary badge-xs absolute -right-1 -top-1 tabular-nums {{ $chatUnread > 0 ? '' : 'hidden' }}">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                            </a>
                            @endif
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
                                        <x-button href="{{ route('bookmarks.create') }}?url={{ urlencode(request()->fullUrl()) }}"
                                           data-entry-modal-trigger
                                           tone="ghost" size="xs"
                                           title="{{ __('Diese Seite merken') }}">
                                            <x-icon name="add" class="text-sm" />
                                            <span>{{ __('Merken') }}</span>
                                        </x-button>
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
                            @php
                                /* Notification-Center (MVP-018): persistente Database-Notifications. */
                                $_notifUser = auth()->user();
                                $_notifUnread = $_notifUser ? $_notifUser->unreadNotifications()->count() : 0;
                                $_notifLatest = $_notifUser ? $_notifUser->notifications()->limit(8)->get() : collect();
                            @endphp
                            <div class="dropdown dropdown-end">
                                <label tabindex="0"
                                       class="btn btn-sm btn-ghost btn-square relative"
                                       title="{{ __('notification.title.center') }}"
                                       aria-label="{{ __('notification.title.center') }}">
                                    <x-icon name="inbox" class="text-base" />
                                    @if ($_notifUnread > 0)
                                        <span class="absolute -top-0.5 -right-0.5 badge badge-xs badge-primary text-[0.6rem] font-semibold tabular-nums">
                                            {{ $_notifUnread > 99 ? '99+' : $_notifUnread }}
                                        </span>
                                    @endif
                                </label>
                                <div tabindex="0" class="dropdown-content header-dropdown-panel z-50 mt-2 w-[min(22rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-0 shadow-lg overflow-hidden">
                                    <div class="px-4 py-2 border-b border-base-200 flex items-center justify-between gap-2">
                                        <span class="text-xs uppercase tracking-wider opacity-60">{{ __('notification.title.center') }}</span>
                                        @if ($_notifUnread > 0)
                                            <form method="POST" action="{{ route('notifications.readAll') }}">
                                                @csrf
                                                <x-button type="submit" tone="ghost" size="xs" title="{{ __('notification.action.mark_all_read') }}">
                                                    <x-icon name="done_all" class="text-sm" />
                                                    <span>{{ __('notification.action.mark_all_read') }}</span>
                                                </x-button>
                                            </form>
                                        @endif
                                    </div>
                                    <div class="max-h-96 overflow-y-auto">
                                        @forelse ($_notifLatest as $_notif)
                                            @php
                                                $_nd = (array) $_notif->data;
                                                $_nUnread = $_notif->read_at === null;
                                            @endphp
                                            <a href="{{ route('notifications.index') }}"
                                               class="flex items-start gap-3 px-4 py-3 hover:bg-base-200 border-b border-base-200 last:border-b-0 {{ $_nUnread ? 'bg-primary/5' : '' }}">
                                                <span class="material-symbols-outlined text-base {{ $_nUnread ? 'text-primary' : 'opacity-50' }}" aria-hidden="true">{{ $_nd['icon'] ?? 'notifications' }}</span>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-sm font-medium truncate">{{ $_nd['title'] ?? '' }}</span>
                                                    @if (! empty($_nd['message']))
                                                        <span class="block text-xs opacity-60 mt-0.5 truncate">{{ $_nd['message'] }}</span>
                                                    @endif
                                                </span>
                                                @if ($_nUnread)
                                                    <span class="mt-1 inline-block h-2 w-2 rounded-full bg-primary shrink-0"></span>
                                                @endif
                                            </a>
                                        @empty
                                            <div class="px-4 py-6 text-center text-sm opacity-60">
                                                <x-icon name="notifications_off" class="text-2xl block mb-1 mx-auto" />
                                                {{ __('notification.title.empty') }}
                                            </div>
                                        @endforelse
                                    </div>
                                    <div class="px-4 py-2 border-t border-base-200 text-right">
                                        <a href="{{ route('notifications.index') }}" class="text-xs link link-hover opacity-70">{{ __('notification.action.show_all') }} →</a>
                                    </div>
                                </div>
                            </div>
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
                                                @php
                                                    $sevColor = match ($_r['severity']) {
                                                        'error' => 'text-error',
                                                        'warning' => 'text-warning',
                                                        default => 'text-info',
                                                    };
                                                @endphp
                                                <span class="material-symbols-outlined text-base {{ $sevColor }}"
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
                                            <span data-theme-label class="material-symbols-outlined text-base leading-none">dark_mode</span>
                                            <span class="text-xs opacity-70">{{ __('Wechseln') }}</span>
                                        </button>
                                    </div>

                                    {{-- Sprache --}}
                                    <div class="px-4 py-3 border-b border-base-200">
                                        <p class="mb-2 text-xs uppercase tracking-wider opacity-60">{{ __('Sprache') }}</p>
                                        <x-locale-switcher variant="inline" />
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
                                                    data-autosubmit
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
                                    <x-icon name="account_circle" class="text-[1.65rem] shrink-0" />
                                    <span class="header-username truncate">{{ Auth::user()->name }}</span>
                                </label>
                                <ul tabindex="0" class="dropdown-content header-dropdown-panel header-menu-list menu z-50 w-[min(14rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                    @foreach ($userNavItems as $item)
                                        @php $active = request()->routeIs($item['route']); @endphp
                                        <li>
                                            <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                               @if ($item['modal']) data-entry-modal-trigger @endif
                                               class="{{ $active ? 'active' : '' }}">
                                                {{ $item['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                    <li>
                                        <x-action-form :action="route('logout')" class="w-full">
                                            <button type="submit" class="flex w-full items-center gap-2 text-error">
                                                ⎋ <span>{{ __('Abmelden') }}</span>
                                            </button>
                                        </x-action-form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-xs">
                            <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                                <span data-theme-label class="material-symbols-outlined text-base leading-none">dark_mode</span>
                            </button>
                            <x-locale-switcher />
                            <x-button href="{{ route('login') }}" tone="primary" size="sm">⇢ {{ __('Anmelden') }}</x-button>
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
               class="wd-badge fixed z-40 -translate-x-full transform shadow-xl transition-transform duration-200 lg:translate-x-0"
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
                    // Nicht registrierte Routen + nicht im Plan enthaltene Module entfernen,
                    // leere Gruppen verwerfen. Resolver lokal (anderer Blade-Scope).
                    $_nav = app(\App\Services\Navigation\NavGate::class);
                    $createGroups = collect($createGroups)
                        ->map(function ($g) use ($_nav) {
                            $g['items'] = collect($g['items'])->filter(fn ($i) => \Illuminate\Support\Facades\Route::has($i['route']) && $_nav->allows($i['route']))->values()->all();
                            return $g;
                        })
                        ->filter(fn ($g) => ! empty($g['items']))
                        ->values()
                        ->all();
                @endphp

                <div class="sidebar-header px-3 py-4">
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
                                    <li class="sidebar-cta-divider"><div class="divider my-1"></div></li>
                                @endif
                                <li class="menu-title">
                                    <span class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/55">{{ $group['label'] }}</span>
                                </li>
                                @foreach ($group['items'] as $item)
                                    <li>
                                        <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
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
                                            <details class="sidebar-subgroup sidebar-subgroup-collapsible"
                                                     data-sidebar-subgroup-key="{{ $group['key'] ?? '' }}"
                                                     @if ($groupActive) open @endif>
                                                <summary class="sidebar-subgroup-label flex items-center gap-2 px-2 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-base-content/60">
                                                    <x-icon :name="$group['icon'] ?? 'label'" class="text-[0.95rem] opacity-70" />
                                                    <span data-sidebar-label class="truncate flex-1">{{ $group['label'] }}</span>
                                                    <x-icon name="expand_more" class="sidebar-subgroup-chevron" />
                                                </summary>
                                                <ul class="menu menu-sm w-full gap-0.5 p-0">
                                                    @foreach (($group['items'] ?? []) as $item)
                                                        @php $active = collect($item['matches'] ?? [$item['route']])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                                        <li>
                                                            <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
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
                                        @endforeach
                                    </div>
                                @else
                                    <ul class="menu menu-sm w-full gap-0.5 p-0 pt-1">
                                        @foreach ($section['items'] as $item)
                                            @php $active = collect($item['matches'] ?? [$item['route']])->contains(fn ($m) => request()->routeIs($m)); @endphp
                                            <li>
                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
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

                <div class="sidebar-footer px-3 py-4">
                    <button type="button"
                            id="app-sidebar-collapse"
                            class="btn btn-sm btn-primary w-full justify-center gap-2"
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

        <script @cspNonce>
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
        <script @cspNonce>
            // Setzt `.wd-has-scroll` auf den Content-Scroll-Container, sobald er
            // überläuft (Scrollbalken sichtbar). CSS reduziert dann das rechte
            // Padding leicht, damit der Abstand mit Balken nicht größer wirkt als
            // links. Ohne Überlauf bleibt der Abstand symmetrisch.
            (function () {
                function update(el) {
                    if (el) el.classList.toggle('wd-has-scroll', el.scrollHeight > el.clientHeight + 1);
                }
                function init() {
                    var els = document.querySelectorAll('.wd-page-fill > main > .wd-page-shell');
                    els.forEach(function (el) {
                        update(el);
                        if (typeof ResizeObserver === 'function') {
                            // Reagiert auf Viewport-/Layout-Änderungen UND auf
                            // wachsenden/schrumpfenden Inhalt (Kinder beobachten).
                            var ro = new ResizeObserver(function () { update(el); });
                            ro.observe(el);
                            Array.prototype.forEach.call(el.children, function (c) { ro.observe(c); });
                        }
                    });
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
                window.addEventListener('load', init);
                window.addEventListener('resize', function () {
                    document.querySelectorAll('.wd-page-fill > main > .wd-page-shell').forEach(update);
                });
            })();
        </script>
        <script @cspNonce>
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
                        // Klick-gesteuert: Header-Menüs UND das Sidebar-„Neu"-Menü.
                        // Sonst (reines CSS-/Focus-Dropdown) schließt das „Neu"-Menü
                        // auf Touch beim Hineinscrollen → unscrollbar.
                        if (!dropdown || !(dropdown.querySelector('.header-dropdown-panel') || dropdown.querySelector('.sidebar-cta-menu'))) return;

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

                // Verhindert, dass das „Neu …"-Menü von selbst aufklappt, wenn der
                // Fokus nach einem Redirect / Dialog-Schließen im „Neu"-Trigger
                // landet (DaisyUI öffnet Dropdowns per :focus-within). Wir nehmen
                // den Fokus dort beim Laden weg — Klick/Tastatur öffnen es weiter.
                function blurStrayCtaFocus() {
                    var active = document.activeElement;
                    if (!active || !active.closest) return;
                    var dd = active.closest('#app-sidebar .dropdown');
                    if (dd && dd.querySelector('.sidebar-cta-menu') && !dd.classList.contains('dropdown-open')) {
                        active.blur();
                    }
                }
                blurStrayCtaFocus();
                window.addEventListener('pageshow', blurStrayCtaFocus);
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
        <script @cspNonce>
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
            <x-maintenance-banner :organization="$_activeOrg" />
            <x-maintenance-window-banner />
            <x-support-banner />
        @endauth
        <div class="mx-auto @yield('wrapper-height-class', 'wd-page-fill') w-full {{ $_wrapperMaxW }} px-2 pt-(--sidebar-gap) pb-[calc(var(--app-footer-h)+var(--sidebar-gap))] md:pb-(--sidebar-gap) sm:px-4 xl:px-8 2xl:px-12 @auth with-help-pad @unless($isLegacyMode) with-sidebar-pad @endunless @endauth">
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

            @auth
            @unless($isLegacyMode ?? false)
                @php
                    // Zugriff ist nach einem Downgrade sofort gesperrt. Der Banner
                    // warnt nur noch vor der geplanten DATENLOESCHUNG – also nur fuer
                    // purgebare Module (aufbewahrungspflichtige bleiben dauerhaft erhalten).
                    $_graceItems = [];
                    if (app()->bound('currentOrganization') && \Illuminate\Support\Facades\Schema::hasTable('plan_module_grace')) {
                        $_purgeable = (array) config('plans.purgeable_on_downgrade', []);
                        foreach (\App\Models\PlanModuleGrace::query()
                            ->where('organization_id', app('currentOrganization')->id)
                            ->whereNull('purged_at')
                            ->where('grace_until', '>', now())
                            ->orderBy('grace_until')
                            ->get() as $_g) {
                            if (($_purgeable[$_g->module] ?? false) !== true) {
                                continue; // aufbewahrungspflichtig → keine Loeschung, kein Hinweis
                            }
                            $_graceItems[] = [
                                'label' => __((string) (config('plans.labels')[$_g->module] ?? $_g->module)),
                                'until' => $_g->grace_until,
                            ];
                        }
                    }
                @endphp
                @if (! empty($_graceItems))
                    <details class="alert alert-warning mb-4 block rounded-2xl px-5 py-3 text-sm shadow-xs [&[open]_.grace-chevron]:rotate-180">
                        <summary class="flex cursor-pointer list-none items-center gap-2 [&::-webkit-details-marker]:hidden">
                            <x-icon name="warning" class="text-base" />
                            <span class="font-semibold">{{ __('Geplante Datenlöschung nach Downgrade') }}</span>
                            <span class="badge badge-warning badge-sm">{{ count($_graceItems) }}</span>
                            <span class="ml-auto text-xs font-normal opacity-70">{{ __('Stichtag') }} {{ $_graceItems[0]['until']->format('d.m.Y') }}</span>
                            <x-icon name="expand_more" class="grace-chevron text-base transition-transform" />
                        </summary>
                        <div class="mt-2 pl-7">
                            <p class="text-xs opacity-80">{{ __('Der Zugriff auf diese Module ist bereits beendet. Ein Upgrade vor dem Stichtag stellt Zugriff und Daten wieder her.') }}</p>
                            <ul class="mt-1 list-disc pl-5">
                                @foreach ($_graceItems as $_gi)
                                    <li>{{ $_gi['label'] }} — {{ __('Daten werden entfernt am') }} {{ $_gi['until']->format('d.m.Y') }}.</li>
                                @endforeach
                            </ul>
                        </div>
                    </details>
                @endif
            @endunless
            @endauth

            {{-- Seitenkopf: Beschreibung + Aktionen der Seite. <x-page-shell> hebt
                 seine Toolbar per @push('page-header') hierher — als eigenes,
                 stehendes Panel ÜBER dem main. Ohne Toolbar wird nichts gerendert
                 (kein Leerraum); den Abstand zum main bringt der gepushte Block mit. --}}
            @stack('page-header')

            {{-- id + tabindex="-1": Ziel des Sprunglinks (WCAG 2.4.1); der
                 <main>-Landmark trägt die implizite role="main". --}}
            <main id="main-content" tabindex="-1" class="wd-surface @yield('main-class', '')">
                @yield('content')
            </main>

            {{-- Seitenfuß: stehendes Pagination-Panel — Gegenstück zum page-header.
                 <x-pagination standing> hebt seinen Block per @push('page-footer')
                 hierher; das Layout rendert ihn als eigenes, stehendes Panel UNTER
                 dem main (volle Content-Breite, scrollt nicht mit). Ohne Pagination
                 (keine Einträge) wird nichts gepusht → kein Leerraum, der Inhalt
                 füllt wie gehabt. --}}
            @stack('page-footer')
        </div>

        <footer id="app-footer" class="fixed inset-x-0 bottom-0 z-50 h-12 bg-base-100 border-t border-base-300 shadow-xs">
            {{-- Mobil: gestapelt (Copyright oben, Version kleiner darunter, Build-
                 Hash ausgeblendet) und kompakt, damit alles in den fixen Footer
                 passt. Ab sm: eine Zeile wie bisher mit voller Versionsangabe. --}}
            <div class="mx-auto flex h-full w-full {{ $_wrapperMaxW }} flex-col items-center justify-center gap-0 px-4 text-center text-[0.65rem] leading-tight text-base-content/70 sm:flex-row sm:text-xs xl:px-8 2xl:px-12">
                <div class="max-w-full"><x-footer-copyright /></div>
                @php($buildHash = \Illuminate\Support\Facades\Cache::remember('build.hash', 3600, fn () => app(\App\Services\Isms\SbomGenerator::class)->resolveGitHash()))
                <span class="whitespace-nowrap text-[0.6rem] text-base-content/40 sm:ml-1 sm:text-xs" title="{{ __('Version') }}"><span class="hidden sm:inline">&middot;&nbsp;</span>v{{ config('app.version', '0.1.0-dev') }}@if ($buildHash)<span class="hidden sm:inline">&nbsp;·&nbsp;{{ $buildHash }}</span>@endif</span>
            </div>
        </footer>

        {{-- Header-/Footer-Höhe live messen und in --app-header-h / --app-footer-h
             schreiben. Nötig, weil der Header je nach Breite mehrzeilig wird (z. B.
             iPad: Logo/Icons + Datumszeile). Die festen 3.5rem/3rem stimmen dann
             nicht mehr → alle darauf basierenden Höhen (wd-page-fill, Sidebar) wären
             falsch und Main stünde nicht bündig über dem Footer. ResizeObserver hält
             die Werte bei Umbruch/Orientierungswechsel aktuell. --}}
        <script @cspNonce>
            (function () {
                var root = document.documentElement;
                var header = document.getElementById('app-header');
                var footer = document.getElementById('app-footer');
                function sync() {
                    // MVP-182: Auto-Hide (wd-header-hidden auf <html>) meldet 0,
                    // damit wd-page-fill die freie Höhe sofort nutzt.
                    if (header) root.style.setProperty('--app-header-h', (root.classList.contains('wd-header-hidden') ? 0 : header.offsetHeight) + 'px');
                    if (footer) root.style.setProperty('--app-footer-h', footer.offsetHeight + 'px');
                }
                sync();
                if (window.ResizeObserver) {
                    var ro = new ResizeObserver(sync);
                    if (header) ro.observe(header);
                    if (footer) ro.observe(footer);
                }
                window.addEventListener('resize', sync);
                window.addEventListener('orientationchange', sync);
                window.addEventListener('load', sync);
            })();
        </script>

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
                <x-button type="button" tone="ghost" class="gap-2" data-entry-modal-close icon="close">{{ __('Abbrechen') }}</x-button>
                <x-button id="action-confirm-submit" type="button" tone="error" class="gap-2" icon="check">{{ __('Ausführen') }}</x-button>
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
                <x-button id="action-notify-ok" type="button" tone="primary" class="gap-2" data-entry-modal-close icon="check">{{ __('OK') }}</x-button>
            </x-slot:actions>
        </x-modal>

        {{-- Layout-Konfig für resources/js/layout.js (Theme-Persistenz + Confirm-/Notify-Dialoge).
             Muss VOR dem deferred app.js-Bundle gesetzt werden (inline, während des Parsens). --}}
        <script @cspNonce>
            window.__layout = {
                themeUpdateUrl: @json(route('account.theme.update')),
                i18n: {
                    confirmTitle: @json(__('Aktion bestätigen')),
                    confirmMessage: @json(__('Möchtest du diese Aktion wirklich ausführen?')),
                    confirmLabel: @json(__('Ausführen')),
                    notifyInfo: @json(__('Hinweis')),
                    notifySuccess: @json(__('Erfolg')),
                    notifyWarning: @json(__('Achtung')),
                    notifyError: @json(__('Fehler')),
                },
            };
        </script>
            @stack('scripts')

            @auth
            {{-- Live-Aktualisierung des Ungelesen-Zählers am Chat-Header-Symbol.
                 window.refreshChatUnread() wird zusätzlich von der Chat-Seite
                 nach Lesen/Empfang aufgerufen; app-weit greift ein leichtes Polling. --}}
            <script @cspNonce>
                (function () {
                    var link = document.getElementById('chat-unread-link');
                    var badge = document.getElementById('chat-unread-badge');
                    if (!link || !badge) return;
                    var url = link.dataset.unreadUrl;
                    window.refreshChatUnread = function () {
                        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (d) {
                                if (!d) return;
                                var n = d.count || 0;
                                badge.textContent = n > 99 ? '99+' : n;
                                badge.classList.toggle('hidden', n <= 0);
                            })
                            .catch(function () {});
                    };
                    setInterval(function () { if (!document.hidden) window.refreshChatUnread(); }, 20000);
                    document.addEventListener('visibilitychange', function () { if (!document.hidden) window.refreshChatUnread(); });
                })();
            </script>
            @endauth

            {{-- In-App-Hilfe-Drawer (MVP-051): einmal pro Seite, befüllt von JS. --}}
            @auth
                <x-help-drawer />
            @endauth
    </body>
</html>
