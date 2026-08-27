{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : app.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="sync-endpoint" content="{{ route('api.internal.sync.commands') }}">
        {{-- Foto-Queue der Offline-Erfassung (Audit 2026-08, W4.1). --}}
        <meta name="sync-attachment-endpoint" content="{{ route('api.internal.sync.attachments') }}">
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
        // Navigations-Kürzel (Feature 037, MVP-721): Ziel-URLs nur mit Recht
        // (NavGate = viewAny der gemappten Policy, wie im Menü); ohne Attribut
        // bleibt das Kürzel in resources/js/shortcuts.js stumm. Legacy-Modus
        // hat andere Routen und keine Übersicht.
        $_shortcutTargets = [];
        if (Auth::check() && $_bodyMode !== 'legacy') {
            $_navGate = app(\App\Services\Navigation\NavGate::class);
            foreach (['diary' => 'diary.index', 'customers' => 'customers.index', 'projects' => 'projects.index', 'new-entry' => 'diary.create'] as $_shortcutKey => $_shortcutRoute) {
                if (\Illuminate\Support\Facades\Route::has($_shortcutRoute) && $_navGate->allows($_shortcutRoute)) {
                    $_shortcutTargets[$_shortcutKey] = route($_shortcutRoute);
                }
            }
        }
        // Als fertiger Attribut-String: Blade-Direktiven direkt hinter @endif
        // (ohne Trennzeichen) werden nicht kompiliert.
        $_shortcutAttrs = implode('', array_map(
            static fn (string $key, string $url): string => ' data-shortcut-' . $key . '="' . e($url) . '"',
            array_keys($_shortcutTargets),
            $_shortcutTargets,
        ));
    @endphp
    <body class="min-h-screen text-base-content {{ $_bodyMode === 'legacy' ? 'bg-base-200' : 'bg-linear-to-b from-base-200 to-base-300' }}" data-mode="{{ $_bodyMode }}"@if ($_helpContextTopic) data-help-context="{{ $_helpContextTopic }}"@endif{!! $_shortcutAttrs !!}>
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
                        <span class="text-muted">/</span>
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
                            // Arbeitsbereiche (Feature 082, MVP-377): aktiver Fokus des Nutzers,
                            // aufgelöst über Session → Preference → Org-Default → Config-Default.
                            // Rein kosmetisch; greift nur im neuen Modus als letzter Filterschritt.
                            $_focusSvc = app(\App\Services\Navigation\NavFocusService::class);
                            $navFocusActive = $isLegacyMode ? 'all' : $_focusSvc->resolveActive($_authUser instanceof \App\Models\User ? $_authUser : null, $_activeOrg, session(\App\Services\Navigation\NavFocusService::SESSION_KEY));
                            $navFocusAvailable = $isLegacyMode ? [] : $_focusSvc->availableFor($_activeOrg);

                            // Navigation (Feature 081, MVP-372): alle Menüstrukturen kommen aus
                            // der zentralen NavigationRegistry — stabile Schlüssel, einheitliches
                            // Plan-/Rechte-Gating (NavGate) und Per-User-Ausblendungen (MVP-374).
                            $_navData = app(\App\Services\Navigation\NavigationRegistry::class)->build($isLegacyMode, $indexRoute, $navFocusActive);
                            $mainNavItems = $_navData['mainNavItems'];
                            $manageNavItems = $_navData['manageNavItems'];
                            $adminNavItems = $_navData['adminNavItems'];
                            $userNavItems = $_navData['userNavItems'];
                            $sidebarSections = $_navData['sidebarSections'];
                            $createGroups = $_navData['createGroups'];
                            $pluginPanelItems = $_navData['pluginPanelItems'];
                            $pluginPanelRoutes = $_navData['pluginPanelRoutes'];

                            $isAdminActive  = collect($adminNavItems)->contains(fn ($i) => request()->routeIs($i['route'])) || request()->routeIs('admin.access.*') || request()->routeIs('admin.imports.*') || request()->routeIs('admin.data.*') || request()->routeIs('admin.remote-support.*');
                            $isManageActive = collect($manageNavItems)->contains(fn ($i) => request()->routeIs($i['route']));
                            $isUserActive = collect($userNavItems)->contains(function ($i) {
                                if (! empty($i['children'])) {
                                    return collect($i['children'])->contains(fn ($c) => request()->routeIs($c['route']));
                                }
                                return isset($i['route']) && request()->routeIs($i['route']);
                            });
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
                                <x-icon name="menu" class="text-[1.25rem]" />
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
                                                ['label' => __('Personal'), 'icon' => 'group', 'routes' => ['org.members.index', 'admin.organizations.index', 'legacy.users.index', 'teams.index', 'payroll.index', 'qualifications.index']],
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
                                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}" @if ($active) aria-current="page" @endif>
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
                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}" class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}" @if ($active) aria-current="page" @endif>
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
                                                ['label' => __('Organisation'), 'icon' => 'corporate_fare', 'routes' => ['admin.organizations.index', 'admin.organizations.edit', 'admin.branding.edit', 'admin.themes.index', 'admin.access.index', 'admin.scope.index', 'admin.workspaces.index']],
                                                ['label' => __('Stammdaten'), 'icon' => 'inventory_2', 'routes' => ['admin.entry-types.index', 'admin.classifications.index', 'admin.classification-requirements.index', 'admin.branch-profiles.index', 'admin.expense-categories.index', 'admin.per-diem-rates.index']],
                                                ['label' => __('Zeitwirtschaft'), 'icon' => 'hourglass_top', 'routes' => ['admin.time-accounts.index', 'admin.time-dimensions.index', 'admin.shift-rotations.index']],
                                                ['label' => __('Regeln & Prozesse'), 'icon' => 'account_tree', 'routes' => ['admin.automations.index', 'admin.notification-rules.index', 'admin.webhooks.index', 'form-templates.index', 'procedures.index', 'admin.report-targets.index', 'whistleblowing.portal.edit']],
                                                ['label' => __('Finanzen & Lohn'), 'icon' => 'payments', 'routes' => ['finance.bank-accounts.index', 'admin.surcharge-rules.index', 'admin.cost-center-rules.index', 'admin.wage-type-mappings.index', 'admin.text-corrections.index']],
                                                ['label' => __('Daten & Schnittstellen'), 'icon' => 'sync_alt', 'routes' => ['admin.data.index', 'admin.integration.inbox', 'admin.cloud-intake.index', 'admin.domain-provider.index', 'admin.ai.index', 'admin.remote-support.pending.index', 'admin.support.grants.index', 'admin.legacy-migration.index']],
                                                ['label' => __('Systembetrieb'), 'icon' => 'monitor_heart', 'routes' => ['audit.index', 'admin.audit-diff.index', 'admin.license.index', 'admin.metrics.index', 'admin.components.index', 'admin.security.index', 'admin.sessions.index', 'admin.integrity.index', 'admin.security-events.index', 'admin.backup.status', 'admin.backup-targets.index', 'admin.scheduler.index', 'admin.problem-reports.index', 'admin.operations.index', 'admin.maintenance-windows.index', 'admin.settings.index']],
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
                                                                   class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}" @if ($active) aria-current="page" @endif>
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
                                                   class="flex! w-full items-center gap-3 {{ $active ? 'menu-active' : '' }}" @if ($active) aria-current="page" @endif>
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

                        {{-- Offline-Sync-Status (Feature 035, §3.5): nur sichtbar,
                             wenn offline oder Änderungen ausstehend/abgelehnt sind;
                             Inhalt pflegt resources/js/offline-sync.js. --}}
                        <a href="{{ route('offline.changes') }}" data-sync-status hidden
                           class="badge badge-warning badge-sm items-center gap-1"
                           title="{{ __('Offline erfasste Änderungen — werden bei Verbindung synchronisiert') }}">
                            <x-icon name="cloud_off" class="text-[0.9rem]" />
                            <span data-sync-pending-count class="tabular-nums">0</span>
                        </a>

                        @isset($attendanceCurrent)
                            @if ($attendanceCurrent)
                                <div class="flex items-center gap-1.5 rounded-box border border-success/40 bg-success/10 px-2 py-1 shadow-xs"
                                     title="{{ __('Eingestempelt seit :time', ['time' => $attendanceCurrent->started_at?->ftime()]) }}"
                                     x-data="stopwatch('{{ $attendanceCurrent->started_at?->toIso8601String() }}')">
                                    <x-icon name="badge" class="text-[1rem] text-success" />
                                    <span class="font-['Space_Grotesk'] text-sm font-semibold tabular-nums text-success"
                                          x-text="displayShort">00:00</span>
                                    <form method="POST" action="{{ route('attendance.clock-out') }}" class="leading-none" data-offline-sync="attendance.clock-out">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-ghost btn-square text-warning" title="{{ __('Ausstempeln') }}" aria-label="{{ __('Ausstempeln') }}">
                                            <x-icon name="logout" />
                                        </button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('attendance.clock-in') }}" class="leading-none" data-offline-sync="attendance.clock-in">
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
                                 das Fallback-Panel mit Suche (Trigger ohne data-help-topic).
                                 Im Legacy-Modus gibt es keine In-App-Hilfe. --}}
                            @unless ($isLegacyMode)
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
                            @endunless
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
                                                <x-icon name="{{ $_bm->icon ?: 'bookmark' }}" class="text-base" />
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
                                                {{-- Icon-only: der volle Text sprengt den schmalen Dropdown-Kopf (DE bricht um). --}}
                                                <x-icon-btn icon="done_all"
                                                            type="submit"
                                                            :label="__('notification.action.mark_all_read')" />
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
                                                <x-icon name="{{ $_nd['icon'] ?? 'notifications' }}" class="text-base {{ $_nUnread ? 'text-primary' : 'opacity-50' }}" />
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-sm font-medium truncate">{{ \App\Support\NotificationText::title($_nd) }}</span>
                                                    @if (($_nMessage = \App\Support\NotificationText::message($_nd)) !== '')
                                                        <span class="block text-xs opacity-60 mt-0.5 truncate">{{ $_nMessage }}</span>
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
                                                <x-icon name="{{ $_r['icon'] }}" class="text-base {{ $sevColor }}" />
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
                                            <x-icon name="dark_mode" class="text-base leading-none" data-theme-label />
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
                                        @if (! empty($item['children']))
                                            @php $childActive = collect($item['children'])->contains(fn ($c) => request()->routeIs($c['route'])); @endphp
                                            <li>
                                                <details @if ($childActive) open @endif>
                                                    <summary class="{{ $childActive ? 'active' : '' }}">{{ $item['label'] }}</summary>
                                                    <ul>
                                                        @foreach ($item['children'] as $child)
                                                            <li>
                                                                <a href="{{ route($child['route'], $child['route_params'] ?? []) }}"
                                                                   @if ($child['modal']) data-entry-modal-trigger @endif
                                                                   class="{{ request()->routeIs($child['route']) ? 'active' : '' }}">
                                                                    {{ $child['label'] }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            </li>
                                        @else
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                                   @if ($item['modal']) data-entry-modal-trigger @endif
                                                   class="{{ $active ? 'active' : '' }}">
                                                    {{ $item['label'] }}
                                                </a>
                                            </li>
                                        @endif
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
                                <x-icon name="dark_mode" class="text-base leading-none" data-theme-label />
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
                @include('partials.shortcuts-dialog')
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
                {{-- createGroups kommen aus der NavigationRegistry (im Header-Block gesetzt, Feature 081 MVP-372). --}}

                <div class="sidebar-header px-3 py-4">
                    {{-- Arbeitsbereich-Umschalter (Feature 082): öffnet den Großkachel-Dialog.
                         Explizite helle Textfarbe (text-base-content löst im .wd-badge-Panel
                         hell auf) + gefüllte base-200-Fläche — auf dem Anthrazit gut sichtbar.
                         Eingeklappt: data-sidebar-label blendet aus, nur das Icon bleibt. --}}
                    @php $_focusMeta = collect($navFocusAvailable)->firstWhere('key', $navFocusActive); @endphp
                    <button type="button"
                            class="mb-2 flex w-full items-center gap-2 rounded-field border border-base-content/20 bg-base-200 px-2.5 py-2 text-left text-base-content transition hover:border-primary hover:bg-base-300"
                            data-open-dialog="focus-dialog"
                            title="{{ __('scope.focus.switcher') }}"
                            aria-haspopup="dialog"
                            aria-label="{{ __('scope.focus.switcher') }}">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-field bg-primary/25 text-primary">
                            <x-icon :name="$_focusMeta['icon'] ?? 'apps'" class="text-[1.1rem]" />
                        </span>
                        <span data-sidebar-label class="min-w-0 flex-1 leading-tight">
                            <span class="block text-[0.6rem] font-medium uppercase tracking-wider text-muted">{{ __('scope.focus.eyebrow') }}</span>
                            <span class="block truncate text-sm font-semibold text-base-content">{{ $_focusMeta['label'] ?? __('scope.focus.all') }}</span>
                        </span>
                        <x-icon name="expand_more" data-sidebar-label class="shrink-0 text-[1.1rem] text-base-content/70" />
                    </button>

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
                                                <summary class="sidebar-subgroup-label flex items-center gap-2 px-2 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-muted">
                                                    <x-icon :name="$group['icon'] ?? 'label'" class="text-[0.95rem] opacity-70" />
                                                    <span data-sidebar-label class="truncate flex-1">{{ $group['label'] }}</span>
                                                    <x-icon name="expand_more" class="sidebar-subgroup-chevron" />
                                                </summary>
                                                <ul class="menu menu-sm w-full gap-0.5 p-0">
                                                    @foreach (($group['items'] ?? []) as $item)
                                                        {{-- Aktiv-Markierung kommt vorberechnet aus der Registry:
                                                             nur der spezifischste Treffer leuchtet. --}}
                                                        @php $active = $item['active'] ?? false; @endphp
                                                        <li>
                                                            <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                                               @if (! empty($item['modal'])) data-entry-modal-trigger @endif
                                                               class="menu-link flex items-center gap-3 {{ $active ? 'menu-active' : '' }}" @if ($active) aria-current="page" @endif
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
                                            @php $active = $item['active'] ?? false; @endphp
                                            <li>
                                                <a href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                                   @if (! empty($item['modal'])) data-entry-modal-trigger @endif
                                                   class="menu-link flex items-center gap-3 {{ $active ? 'menu-active' : '' }}" @if ($active) aria-current="page" @endif
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

                <div class="sidebar-footer flex flex-col gap-2 px-3 py-4">
                    {{-- Menü anpassen (Feature 081, MVP-374): persönliche Ausblendungen.
                         Plain `btn` (KEIN btn-ghost): setzt --btn-fg über
                         var(--color-base-content) — löst in der .wd-badge-Sidebar lokal zu
                         hell auf (btn-ghost erbt dagegen via currentColor die dunkle
                         Body-Textfarbe → unsichtbar). Gleiche Größe wie die übrigen
                         btn-sm-Buttons; base-200-Füllung + Rand machen ihn erkennbar. --}}
                    <a href="{{ route('me.navigation.customize') }}"
                       class="btn btn-sm w-full justify-center gap-2 border border-base-content/20 hover:border-primary {{ request()->routeIs('me.navigation.*') ? 'btn-active' : '' }}"
                       aria-label="{{ __('scope.nav.customize') }}"
                       title="{{ __('scope.nav.customize') }}">
                        <x-icon name="edit" />
                        <span data-sidebar-label>{{ __('scope.nav.customize') }}</span>
                    </a>
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

        {{-- Arbeitsbereich-Dialog (Feature 082, MVP-378). --}}
        @include('partials.focus-dialog')
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
        <div class="mx-auto @yield('wrapper-height-class', 'wd-page-fill') w-full {{ $_wrapperMaxW }} px-2 pt-(--sidebar-gap) pb-[calc(var(--app-footer-h)+var(--sidebar-gap))] md:pb-(--sidebar-gap) sm:px-4 xl:px-8 2xl:px-12 @auth @unless($isLegacyMode) with-help-pad with-sidebar-pad @endunless @endauth">
            {{-- Zentrale Flashes (I4): einzige Render-Stelle für success/error/
                 warning/info — Views rendern diese Keys NICHT erneut
                 (Gate: DuplicateFlashRuleTest). role macht sie für
                 Screenreader wahrnehmbar. --}}
            @if (session('success'))
                <div role="status" class="alert alert-success mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div role="alert" class="alert alert-error mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('warning'))
                {{-- Hinweis, der nichts verhindert hat: Der Vorgang lief durch,
                     aber jemand soll ihn nachsehen (z. B. fehlende
                     Pflichtnachweise bei der Zahlungsfreigabe, Feature 117). --}}
                <div role="alert" class="alert alert-warning mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('warning') }}
                </div>
            @endif
            @if (session('info'))
                <div role="status" class="alert alert-info mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
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
                    if (app()->bound('currentOrganization')) {
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
                <span class="whitespace-nowrap text-[0.6rem] text-muted sm:ml-1 sm:text-xs" title="{{ __('Version') }}"><span class="hidden sm:inline">&middot;&nbsp;</span>v{{ config('app.version', '0.1.0-dev') }}@if ($buildHash)<span class="hidden sm:inline">&nbsp;·&nbsp;{{ $buildHash }}</span>@endif</span>
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

            {{-- In-App-Hilfe-Drawer (MVP-051): einmal pro Seite, befüllt von JS.
                 Im Legacy-Modus nicht angeboten. --}}
            @auth
                @unless ($isLegacyMode)
                    <x-help-drawer />
                @endunless
            @endauth
    </body>
</html>
