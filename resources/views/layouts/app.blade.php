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
    <body class="min-h-screen">
        @php
            $currentMode = session('work_mode', 'legacy');
            $legacyConfigured = filled(config('database.connections.legacy.database'));
            $effectiveMode = $currentMode === 'legacy' && $legacyConfigured ? 'legacy' : 'new';
            $indexRoute = $effectiveMode === 'legacy' ? 'legacy.diary.index' : 'diary.index';
            $createRoute = $effectiveMode === 'legacy' ? 'legacy.diary.create' : 'diary.create';
            $originRoute = request()->route()?->getName() ?? 'home';
            $isLegacyMode = $effectiveMode === 'legacy';
            $legacyUserId = \App\Support\LegacyRoleResolver::resolveLegacyUserId(Auth::user());
            $isLegacyAdmin = \App\Support\LegacyRoleResolver::isAdmin(Auth::user());
            $currentLocale = app()->getLocale();
            $supportedLocales = [
                'de' => ['label' => __('Deutsch'),  'code' => 'DE'],
                'en' => ['label' => __('Englisch'), 'code' => 'EN'],
            ];
        @endphp

        <header class="fixed inset-x-0 top-0 z-50 border-b border-base-300 bg-base-100 shadow-xs">
            <div class="navbar mx-auto w-full max-w-screen-2xl px-4 xl:px-8 2xl:px-12 min-h-14">
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
                                    ['route' => 'legacy.diary.week',        'label' => __('Wochenansicht'),  'modal' => false, 'matches' => ['legacy.diary.week']],
                                    ['route' => $indexRoute,                'label' => __('Arbeitsliste'),   'modal' => false, 'matches' => [$indexRoute]],
                                    ['route' => 'legacy.overview.index',    'label' => __('Überblick'),      'modal' => false, 'matches' => ['legacy.overview.index']],
                                    ['route' => 'legacy.oncall.index',      'label' => __('Dienste'),        'modal' => false, 'matches' => ['legacy.oncall.*', 'legacy.notdienst.*']],
                                    ['route' => 'legacy.archive.index',     'label' => __('Archiv'),         'modal' => false, 'matches' => ['legacy.archive.*']],
                                    ['route' => 'legacy.callcenter.notdienst', 'label' => __('Callcenter'),  'modal' => false, 'matches' => ['legacy.callcenter.*']],
                                ]
                                : [
                                    ['route' => $indexRoute,                'label' => __('Arbeitsliste'),   'modal' => false, 'matches' => [$indexRoute]],
                                    ['route' => 'week.index',               'label' => __('Wochenansicht'),  'modal' => false, 'matches' => ['week.index']],
                                    ['route' => 'kanban.index',             'label' => __('Kanban'),         'modal' => false, 'matches' => ['kanban.index']],
                                    ['route' => 'duties.index',             'label' => __('Dienste'),        'modal' => false, 'matches' => ['duties.*']],
                                ];

                            $adminNavItems = [];
                            if ($isLegacyAdmin) {
                                $adminNavItems[] = ['route' => 'legacy.users.index', 'label' => __('Mitarbeiter'), 'modal' => false];
                                $adminNavItems[] = ['route' => 'holidays.index',     'label' => __('Feiertage'),   'modal' => false];
                                $adminNavItems[] = ['route' => 'audit.index',        'label' => __('Audit-Log'),   'modal' => false];
                                $adminNavItems[] = ['route' => 'admin.legacy-migration.index', 'label' => __('Legacy-Migration'), 'modal' => false];
                            }

                            $userNavItems = [];
                            if (! $isLegacyMode) {
                                $userNavItems[] = ['route' => 'account.profile.edit',  'label' => __('Profil bearbeiten'), 'modal' => true];
                                $userNavItems[] = ['route' => 'account.password.edit', 'label' => __('Passwort ändern'),  'modal' => true];
                            } else {
                                $userNavItems[] = ['route' => 'legacy.account.password.edit', 'label' => __('Passwort ändern'), 'modal' => true];
                            }
                            $userNavItems[] = ['route' => 'profile.api-tokens.index', 'label' => __('API-Tokens'), 'modal' => false];

                            $isAdminActive = collect($adminNavItems)->contains(fn ($i) => request()->routeIs($i['route']));
                            $isUserActive = collect($userNavItems)->contains(fn ($i) => request()->routeIs($i['route']));
                        @endphp

                        {{-- Inline-Hauptnavigation (xl+) --}}
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

                        {{-- Fallback-Hauptnavigation (< xl) --}}
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

                        {{-- Admin-Menü --}}
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
                                <div class="join">
                                    <form method="POST" action="{{ route('mode.switch', 'legacy') }}" class="join-item">
                                        @csrf
                                        <input type="hidden" name="origin" value="{{ $originRoute }}">
                                        <button type="submit" class="btn btn-sm {{ $currentMode === 'legacy' ? 'btn-primary' : 'btn-ghost' }}">Legacy</button>
                                    </form>
                                    <form method="POST" action="{{ route('mode.switch', 'new') }}" class="join-item">
                                        @csrf
                                        <input type="hidden" name="origin" value="{{ $originRoute }}">
                                        <button type="submit" class="btn btn-sm {{ $currentMode === 'new' ? 'btn-primary' : 'btn-ghost' }}">Neu</button>
                                    </form>
                                </div>
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

        <div class="mx-auto flex @yield('wrapper-height-class', 'min-h-screen') w-full max-w-7xl flex-col px-4 pb-20 pt-24 lg:px-10">
            @if (session('success'))
                <div class="alert alert-success mb-4 rounded-2xl px-5 py-3 text-sm shadow-xs">
                    {{ session('success') }}
                </div>
            @endif

            <main class="flex-1 @yield('main-class', '')">
                @yield('content')
            </main>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-xs">
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

                            if (confirmTitle) {
                                confirmTitle.textContent = form.getAttribute('data-confirm-title') || 'Aktion bestätigen';
                            }

                            if (confirmMessage) {
                                confirmMessage.textContent = form.getAttribute('data-confirm-message') || 'Möchtest du diese Aktion wirklich ausführen?';
                            }

                            confirmSubmit.textContent = form.getAttribute('data-confirm-label') || 'Ausführen';

                            if (typeof confirmDialog.showModal === 'function') {
                                confirmDialog.showModal();
                            } else {
                                var fallback = window.confirm(confirmMessage ? confirmMessage.textContent : 'Möchtest du diese Aktion wirklich ausführen?');
                                if (fallback) {
                                    pendingForm.submit();
                                }
                                pendingForm = null;
                            }
                        });

                        confirmSubmit.addEventListener('click', function () {
                            if (!pendingForm) {
                                return;
                            }

                            var formToSubmit = pendingForm;
                            pendingForm = null;
                            confirmDialog.close();
                            formToSubmit.submit();
                        });

                        confirmDialog.addEventListener('close', function () {
                            pendingForm = null;
                        });
                    }
                })();
            </script>
    </body>
</html>
