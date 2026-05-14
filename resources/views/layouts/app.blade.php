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
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Two+Tone" rel="stylesheet">
        <style>
            :root { --sidebar-w: 16rem; }
            body.sidebar-collapsed { --sidebar-w: 4rem; }
            #app-sidebar { width: var(--sidebar-w); transition: width 200ms ease; }
            @media (min-width: 1024px) {
                .with-sidebar-pad { padding-left: var(--sidebar-w); transition: padding-left 200ms ease; }
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
            #app-sidebar .material-icons-two-tone { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
            /* Aktiver Eintrag: dezent getönt, damit das Two-Tone-Icon erkennbar bleibt */
            #app-sidebar .menu :where(li) > .menu-active,
            #app-sidebar .menu :where(li) > .menu-active:hover,
            #app-sidebar .menu :where(li) > .menu-active:focus {
                background-color: color-mix(in oklab, var(--color-primary) 15%, transparent) !important;
                color: var(--color-primary) !important;
                font-weight: 600;
                box-shadow: inset 3px 0 0 var(--color-primary);
            }
            #app-sidebar .menu :where(li) > .menu-active .material-icons-two-tone { color: var(--color-primary) !important; }

            /* Dark-Theme: dunkler Verlauf für Content + helleres Icon-Rendering */
            html[data-theme="dim"] body,
            html[data-theme="dark"] body,
            html[data-theme="night"] body,
            html[data-theme="business"] body,
            html[data-theme="black"] body,
            html[data-theme="luxury"] body,
            html[data-theme="dracula"] body,
            html[data-theme="coffee"] body,
            html[data-theme="forest"] body {
                background-image: linear-gradient(to bottom right, #1f242c, #131820, #1f242c) !important;
            }
            html[data-theme="dim"] .material-icons-two-tone,
            html[data-theme="dark"] .material-icons-two-tone,
            html[data-theme="night"] .material-icons-two-tone,
            html[data-theme="business"] .material-icons-two-tone,
            html[data-theme="black"] .material-icons-two-tone,
            html[data-theme="luxury"] .material-icons-two-tone,
            html[data-theme="dracula"] .material-icons-two-tone,
            html[data-theme="coffee"] .material-icons-two-tone,
            html[data-theme="forest"] .material-icons-two-tone {
                /* Two-Tone-Icons sind als Schwarz-Glyphen mit interner Alpha designt -
                   im Dark-Mode invertieren, damit sie hell erscheinen */
                filter: invert(1) hue-rotate(180deg) brightness(1.35);
            }

            /* Innerhalb farbiger Buttons (btn-primary/secondary/accent/info/success/warning/error/neutral)
               soll das Icon komplett in der Buttontext-Farbe (idR. weiß) erscheinen.
               Das Two-Tone-Font hat einen fest eincodierten, halbtransparenten Sekundärton, der
               auf farbigem Grund grau/dunkel wirkt. Mit `brightness(0) invert(1)` werden beide
               Tonspuren auf reines Weiß gezogen. */
            .btn-primary .material-icons-two-tone,
            .btn-secondary .material-icons-two-tone,
            .btn-accent .material-icons-two-tone,
            .btn-info .material-icons-two-tone,
            .btn-success .material-icons-two-tone,
            .btn-warning .material-icons-two-tone,
            .btn-error .material-icons-two-tone,
            .btn-neutral .material-icons-two-tone,
            .badge-primary .material-icons-two-tone,
            .badge-secondary .material-icons-two-tone,
            .badge-accent .material-icons-two-tone,
            .badge-info .material-icons-two-tone,
            .badge-success .material-icons-two-tone,
            .badge-warning .material-icons-two-tone,
            .badge-error .material-icons-two-tone,
            .badge-neutral .material-icons-two-tone,
            .alert-info .material-icons-two-tone,
            .alert-success .material-icons-two-tone,
            .alert-warning .material-icons-two-tone,
            .alert-error .material-icons-two-tone {
                filter: brightness(0) invert(1) !important;
                color: #fff !important;
                opacity: 1 !important;
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
    <body class="min-h-screen bg-gradient-to-br from-neutral-100 via-neutral-200 to-neutral-100" data-mode="{{ $_bodyMode }}">
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

        <header id="app-header" class="fixed inset-x-0 top-0 z-50 bg-base-100 shadow-xs">
            <div class="navbar w-full px-4 xl:px-8 2xl:px-12 min-h-14">
                <div class="navbar-start min-w-0 flex-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 group min-w-0">
                        <span class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary transition group-hover:opacity-80 shrink-0">WorkDiary</span>
                        <span class="text-base-content/40">/</span>
                        <span class="font-['Space_Grotesk'] font-semibold text-base-content truncate">@yield('nav-title', __('Tagebuch'))</span>
                    </a>
                </div>
                <div class="navbar-end gap-2">
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
                                    $manageNavItems[] = ['route' => 'flex.admin',                    'label' => __('Gleitzeit Team'),   'icon' => 'groups',           'modal' => false];
                                    $adminNavItems[]  = ['route' => 'admin.organizations.index',     'label' => __('Organisationen'),   'icon' => 'corporate_fare',   'modal' => false];
                                }
                                $adminNavItems[] = ['route' => 'audit.index',                       'label' => __('Audit-Log'),        'icon' => 'fact_check',       'modal' => false];
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
                                <ul tabindex="0" class="dropdown-content menu z-50 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow">
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
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow">
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
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow">
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
                                        <span class="material-icons-two-tone text-[1.1rem] leading-none" aria-hidden="true">manage_accounts</span>
                                        <span class="hidden sm:inline">{{ __('Verwaltung') }}</span>
                                    </label>
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-60 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        @foreach ($manageNavItems as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                    <span class="material-icons-two-tone text-[1.1rem] leading-none" aria-hidden="true">{{ $item['icon'] ?? 'tune' }}</span>
                                                    <span class="truncate">{{ $item['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($adminNavItems))
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-sm {{ $isAdminActive ? 'btn-primary' : 'btn-ghost' }} gap-1" title="{{ __('Administration') }}">
                                        <span class="material-icons-two-tone text-[1.1rem] leading-none" aria-hidden="true">settings</span>
                                        <span class="hidden sm:inline">{{ __('Admin') }}</span>
                                    </label>
                                    <ul tabindex="0" class="dropdown-content menu z-50 w-60 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                        @foreach ($adminNavItems as $item)
                                            @php $active = request()->routeIs($item['route']); @endphp
                                            <li>
                                                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 {{ $active ? 'menu-active' : '' }}">
                                                    <span class="material-icons-two-tone text-[1.1rem] leading-none" aria-hidden="true">{{ $item['icon'] ?? 'tune' }}</span>
                                                    <span class="truncate">{{ $item['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif

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
                                <ul tabindex="0" class="dropdown-content menu z-50 w-40 rounded-box border border-base-300 bg-base-100 p-1 shadow">
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
                                <ul tabindex="0" class="dropdown-content menu z-50 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow">
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
                                <ul tabindex="0" class="dropdown-content menu z-50 w-40 rounded-box border border-base-300 bg-base-100 p-1 shadow">
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
        </header>

        @auth
        @unless ($isLegacyMode)
        {{-- Sidebar: persistent ab lg, sonst Drawer --}}
        <aside id="app-sidebar"
               class="fixed top-14 bottom-12 left-0 z-40 -translate-x-full transform bg-base-100 shadow-sm transition-transform duration-200 lg:translate-x-0"
               aria-label="{{ __('Hauptnavigation') }}"
               data-sidebar>
            <div class="flex h-[calc(100dvh-3.5rem-3rem)] flex-col gap-3 overflow-y-auto overflow-x-hidden px-2 py-3">
                <a href="{{ route($createRoute) }}" data-entry-modal-trigger
                   class="sidebar-cta btn btn-sm btn-primary w-full gap-2"
                   title="{{ __('Neuer Eintrag') }}">
                    <span class="material-icons-two-tone" aria-hidden="true">add_circle</span>
                    <span class="sidebar-cta-text">{{ __('Neuer Eintrag') }}</span>
                </a>

                <div>
                    <p data-sidebar-section class="px-2 pb-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/50 transition-opacity duration-150">{{ __('Arbeiten') }}</p>
                    <ul class="menu menu-sm w-full gap-0.5 p-0">
                        @foreach ($mainNavItems as $item)
                            @php $active = collect($item['matches'])->contains(fn ($m) => request()->routeIs($m)); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @if ($item['modal']) data-entry-modal-trigger @endif
                                   class="menu-link flex items-center gap-3 {{ $active ? 'menu-active' : '' }}"
                                   title="{{ $item['label'] }}">
                                    <span class="material-icons-two-tone" aria-hidden="true">{{ $item['icon'] ?? 'circle' }}</span>
                                    <span data-sidebar-label class="truncate transition-opacity duration-150">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-auto pt-2">
                    <button type="button"
                            id="app-sidebar-collapse"
                            class="btn btn-sm btn-ghost w-full justify-center gap-2"
                            aria-label="{{ __('Sidebar ein-/ausklappen') }}"
                            title="{{ __('Sidebar ein-/ausklappen') }}">
                        <span class="material-icons-two-tone" data-sidebar-collapse-icon aria-hidden="true">chevron_left</span>
                        <span data-sidebar-label>{{ __('Einklappen') }}</span>
                    </button>
                </div>
            </div>
        </aside>
        <div id="app-sidebar-backdrop" class="fixed inset-x-0 top-14 bottom-12 z-30 hidden bg-black/40 backdrop-blur-[1px] lg:hidden" data-sidebar-backdrop></div>
        @endunless
        @endauth

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

        <div class="mx-auto flex @yield('wrapper-height-class', 'min-h-screen') w-full max-w-screen-2xl flex-col px-4 pb-20 pt-24 xl:px-8 2xl:px-12 @auth @unless($isLegacyMode) with-sidebar-pad @endunless @endauth">
            @if (session('success'))
                <div class="alert alert-success mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('success') }}
                </div>
            @endif

            <main class="flex-1 @yield('main-class', '')">
                @yield('content')
            </main>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 h-12 bg-base-100 shadow-xs">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
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
