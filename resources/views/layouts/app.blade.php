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
        @endphp

        <header class="fixed inset-x-0 top-0 z-50 border-b border-base-300 bg-base-100 shadow-sm">
            <div class="navbar mx-auto w-full max-w-screen-2xl flex-nowrap px-4 xl:px-8 2xl:px-12 min-h-14">
                <div class="navbar-start min-w-0 flex-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 group min-w-0">
                        <span class="font-['Space_Grotesk'] text-xs uppercase tracking-[0.35em] text-primary transition group-hover:opacity-80 shrink-0">WorkDiary</span>
                        <span class="text-base-content/40">/</span>
                        <span class="font-['Space_Grotesk'] font-semibold text-base-content truncate">@yield('nav-title', __('Tagebuch'))</span>
                    </a>
                </div>
                <div class="navbar-end flex-nowrap gap-2 whitespace-nowrap">
                    @auth
                        @if ($isLegacyMode)
                            <nav class="hidden flex-nowrap items-center gap-1 lg:flex">
                                <a href="{{ route('legacy.diary.week') }}" class="btn btn-sm btn-ghost {{ request()->routeIs('legacy.diary.week') ? 'btn-active' : '' }}">{{ __('Wochenansicht') }}</a>
                                <a href="{{ route($indexRoute) }}" class="btn btn-sm btn-ghost {{ request()->routeIs('legacy.diary.index') ? 'btn-active' : '' }}">{{ __('Arbeitsliste') }}</a>
                                <a href="{{ route('legacy.oncall.index') }}" class="btn btn-sm btn-ghost {{ request()->routeIs('legacy.oncall.*', 'legacy.notdienst.*') ? 'btn-active' : '' }}">{{ __('Bereitschaft & Notdienst') }}</a>
                                <a href="{{ route('legacy.archive.index') }}" class="btn btn-sm btn-ghost {{ request()->routeIs('legacy.archive.*') ? 'btn-active' : '' }}">{{ __('Archiv') }}</a>
                                <a href="{{ route('legacy.callcenter.notdienst') }}" class="btn btn-sm btn-ghost {{ request()->routeIs('legacy.callcenter.*') ? 'btn-active' : '' }}">{{ __('Callcenter') }}</a>
                                @if ($isLegacyAdmin)
                                    <div class="dropdown dropdown-end">
                                        <label tabindex="0" class="btn btn-sm btn-ghost {{ request()->routeIs('legacy.users.*') ? 'btn-active' : '' }}">⚙ {{ __('Admin') }} ▾</label>
                                        <ul tabindex="0" class="dropdown-content menu z-50 mt-1 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                            <li><a href="{{ route('legacy.users.index') }}">{{ __('Mitarbeiter') }}</a></li>
                                        </ul>
                                    </div>
                                @endif
                            </nav>
                            <div class="dropdown dropdown-end lg:hidden">
                                <label tabindex="0" class="btn btn-sm btn-ghost">☰ {{ __('Navigation') }}</label>
                                <ul tabindex="0" class="dropdown-content menu z-50 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                    <li><a href="{{ route('legacy.diary.week') }}">{{ __('Wochenansicht') }}</a></li>
                                    <li><a href="{{ route($indexRoute) }}">{{ __('Arbeitsliste') }}</a></li>
                                    <li><a href="{{ route('legacy.oncall.index') }}">{{ __('Bereitschaft & Notdienst') }}</a></li>
                                    <li><a href="{{ route('legacy.archive.index') }}">{{ __('Archiv') }}</a></li>
                                    <li><a href="{{ route('legacy.callcenter.notdienst') }}">{{ __('Callcenter') }}</a></li>
                                    @if ($isLegacyAdmin)
                                        <li class="menu-title pt-2"><span>{{ __('Admin') }}</span></li>
                                        <li><a href="{{ route('legacy.users.index') }}">{{ __('Mitarbeiter') }}</a></li>
                                    @endif
                                </ul>
                            </div>
                            <a href="{{ route($createRoute) }}" class="btn btn-sm btn-primary">{{ __('+ Neuer Eintrag') }}</a>
                        @else
                            <div class="flex flex-nowrap items-center gap-2">
                                <a href="{{ route($indexRoute) }}" class="btn btn-sm btn-ghost">▤ {{ __('Arbeitsliste') }}</a>
                                @if ($isLegacyAdmin)
                                    <div class="dropdown dropdown-end">
                                        <label tabindex="0" class="btn btn-sm btn-ghost">⚙ {{ __('Admin') }} ▾</label>
                                        <ul tabindex="0" class="dropdown-content menu z-50 mt-1 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                            <li><a href="{{ route('legacy.users.index') }}">{{ __('Mitarbeiter') }}</a></li>
                                        </ul>
                                    </div>
                                @endif
                                <a href="{{ route($createRoute) }}" class="btn btn-sm btn-primary">{{ __('+ Neuer Eintrag') }}</a>
                            </div>
                        @endif
                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-sm">
                            <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                                <span data-theme-label class="text-base leading-none">◐</span>
                            </button>
                            @php $currentLocale = app()->getLocale(); @endphp
                            <form method="POST" action="{{ route('locale.switch', $currentLocale === 'de' ? 'en' : 'de') }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost btn-square" title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}">
                                    <span class="text-base leading-none">{{ $currentLocale === 'de' ? '🇩🇪' : '🇬🇧' }}</span>
                                </button>
                            </form>
                            @if ($legacyConfigured)
                                <div class="join">
                                    <form method="POST" action="{{ route('mode.switch', 'legacy') }}" class="join-item">
                                        @csrf
                                        <input type="hidden" name="origin" value="{{ $originRoute }}">
                                        <button type="submit" class="btn btn-sm {{ $currentMode === 'legacy' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Legacy') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('mode.switch', 'new') }}" class="join-item">
                                        @csrf
                                        <input type="hidden" name="origin" value="{{ $originRoute }}">
                                        <button type="submit" class="btn btn-sm {{ $currentMode === 'new' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Neu') }}</button>
                                    </form>
                                </div>
                            @endif
                            <div class="dropdown dropdown-end">
                                <label tabindex="0" class="btn btn-sm btn-ghost">⎋ {{ Auth::user()->name }} ▾</label>
                                <ul tabindex="0" class="dropdown-content menu z-50 mt-1 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                                    <li><a href="{{ route('legacy.account.password.edit') }}">{{ __('Passwort ändern') }}</a></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left">{{ __('Abmelden') }}</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 rounded-box border border-base-300 bg-base-200/70 p-1.5 shadow-sm">
                            <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                                <span data-theme-label class="text-base leading-none">◐</span>
                            </button>
                            @php $currentLocale = app()->getLocale(); @endphp
                            <form method="POST" action="{{ route('locale.switch', $currentLocale === 'de' ? 'en' : 'de') }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost btn-square" title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}">
                                    <span class="text-base leading-none">{{ $currentLocale === 'de' ? '🇩🇪' : '🇬🇧' }}</span>
                                </button>
                            </form>
                            <a href="{{ route('login') }}" class="btn btn-sm btn-primary">⇢ {{ __('Anmelden') }}</a>
                        </div>
                    @endauth
                </div>
            </div>
        </header>

        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 pb-20 pt-24 lg:px-10">
            @if (session('success'))
                <div class="alert alert-success mb-4 rounded-box px-5 py-3 text-sm shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            <main class="flex-1">
                @yield('content')
            </main>
        </div>

        <footer class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-sm">
            <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-center px-4 py-3 text-xs text-base-content/70 xl:px-8 2xl:px-12">
                &copy; {{ date('Y') }} WorkDiary. {{ __('Alle Rechte vorbehalten.') }}
            </div>
        </footer>

        <dialog id="action-confirm-dialog" class="modal">
                <div class="modal-box">
                    <h3 id="action-confirm-title" class="text-lg font-bold">Aktion bestätigen</h3>
                    <p id="action-confirm-message" class="py-4 text-sm text-base-content/75">Möchtest du diese Aktion wirklich ausführen?</p>
                    <div class="modal-action">
                        <form method="dialog">
                            <button class=" btn btn-sm btn-ghost">{{ __('Abbrechen') }}</button>
                        </form>
                        <button id="action-confirm-submit" type="button" class=" btn btn-sm btn-error">{{ __('Ausführen') }}</button>
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
