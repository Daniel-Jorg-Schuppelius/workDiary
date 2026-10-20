{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : layout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    use App\Services\CustomerPortal\{PortalNavigation, PortalPreview, PortalVisibility};

    /** @var \App\Models\Platform\User|null $portalUser */
    $portalUser = auth('customer')->user();
    $portalCustomer = $portalUser?->customer;
    // Vorschau als Kunde (Phase 153): Leiste statt Abmelden, Sicherheitsseiten und Umschalter ausgeblendet.
    $portalPreview = app(PortalPreview::class);
    $portalPreviewState = $portalPreview->activeState(request());
    // Bereiche aus EINER Liste (Phase 154, E5): nur, was für diesen Kunden freigegeben ist (Default-Deny).
    $portalNav = $portalUser !== null && $portalCustomer !== null ? app(PortalNavigation::class)->items($portalCustomer) : [];
    $brandName = isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary');
    $brandLogo = isset($branding) && $branding ? $branding->logoUrl() : null;
    $portalTitle = trim($__env->yieldContent('title', $title ?? ''));
    $pageTitle = ($portalPreviewState !== null ? __('customer_portal.preview.title_prefix') . ' – ' : '')
        . ($portalTitle !== '' ? $portalTitle . ' | ' : '')
        . __('customer_portal.layout.brand_subtitle') . ' | ' . $brandName;
    $navLink = fn (array $item): string => route($item['route']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ config('theme.auto.light') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Theme + Anti-Flash (eine Quelle mit dem App-Layout, MVP-1241): Seed, Org-Themes, data-theme vor dem ersten Paint. --}}
    @include('partials.theme-seed')
    <title>{{ $pageTitle }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/logo/workdiary-mark-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo/workdiary-mark-192.png') }}">
    <meta name="theme-color" content="#1d232a" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    @include('partials.font-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Layout-Konfig für resources/js/layout.js: der Umschalter speichert die Wahl am Portalkonto;
         in der Vorschau (nur lesend) gibt es keinen Speicherweg und keinen Umschalter. --}}
    <script @cspNonce>
        window.__layout = {
            themeUpdateUrl: @json($portalUser !== null && $portalPreviewState === null ? route('customer.theme.update') : null),
        };
    </script>
</head>
<body class="bg-base-200 min-h-screen text-base-content">
    {{-- Sprunglink (WCAG 2.4.1) — gleiches Muster wie layouts/app; MUSS das
         erste fokussierbare Element sein. --}}
    <a href="#main-content"
       class="wd-skip-link sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-100 focus:rounded-box focus:bg-primary focus:px-4 focus:py-2 focus:font-semibold focus:text-primary-content focus:shadow-lg focus:ring-2 focus:ring-primary/60">
        {{ __('Zum Inhalt springen') }}
    </a>
    {{-- Mobile Navigation als Drawer über eine Checkbox (daisyUI, ohne Skript, E6). --}}
    <div class="drawer">
        <input id="portal-nav-drawer" type="checkbox" class="drawer-toggle" aria-label="{{ __('customer_portal.layout.menu_open') }}">
        <div class="drawer-content flex min-h-screen flex-col">
            <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100 shadow-xs">
                @if ($portalPreviewState !== null)
                    <div role="status" class="bg-warning text-warning-content">
                        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 text-sm">
                            <span class="flex items-center gap-2">
                                <x-icon name="visibility" />
                                <span>{{ __('customer_portal.preview.banner', ['name' => $portalUser?->name, 'customer' => $portalCustomer?->name]) }} {{ __('customer_portal.preview.banner_until', ['time' => $portalPreview->expiresAt($portalPreviewState)->ftime()]) }}</span>
                            </span>
                            <form method="POST" action="{{ route('customer.preview.stop') }}">
                                @csrf
                                <x-button type="submit" tone="neutral" size="sm" icon="visibility_off"><span>{{ __('customer_portal.preview.stop') }}</span></x-button>
                            </form>
                        </div>
                    </div>
                @endif
                <div class="mx-auto flex min-h-14 w-full max-w-6xl items-center justify-between gap-3 px-4 py-2">
                    <div class="flex min-w-0 items-center gap-2">
                        @if ($portalNav !== [])
                            <label for="portal-nav-drawer" class="btn btn-ghost btn-sm btn-square md:hidden" title="{{ __('customer_portal.layout.menu_open') }}" aria-label="{{ __('customer_portal.layout.menu_open') }}">
                                <x-icon name="menu" class="text-base" />
                            </label>
                        @endif
                        <a href="{{ route('customer.dashboard') }}" class="flex min-w-0 items-center gap-3">
                            @if ($brandLogo)
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-9 w-auto max-w-36 shrink-0 object-contain">
                            @else
                                <span class="font-['Space_Grotesk'] text-lg font-bold tracking-tight">{{ $brandName }}</span>
                            @endif
                            <span class="hidden truncate text-sm text-muted sm:inline">{{ __('customer_portal.layout.brand_subtitle') }}</span>
                        </a>
                    </div>
                    <div class="flex shrink-0 items-center gap-1" role="group" aria-label="{{ __('Benutzermenü') }}">
                        @if ($portalUser)
                            <x-icon-btn icon="search" size="sm" :href="route('customer.search')" :label="__('customer_portal.layout.search')" />
                            @php($portalHelpTopic = app(\App\Services\Help\HelpContextResolver::class)->currentTopicFor(request()))
                            <x-icon-btn icon="help" size="sm" :label="__('customer_help.nav')"
                                        :href="$portalHelpTopic !== null && str_starts_with($portalHelpTopic, 'customer-portal.') && app(\App\Http\Controllers\CustomerPortal\HelpController::class)->allowed($portalHelpTopic) ? route('customer.help.show', $portalHelpTopic) : route('customer.help.index')" />
                        @endif
                        @if ($portalPreviewState === null)
                            {{-- raw-markup-ok: Theme-Umschalter wie im App-Layout — layout.js braucht data-theme-toggle am Knopf und data-theme-label am Symbol --}}
                            <button type="button" data-theme-toggle aria-label="{{ __('customer_portal.layout.theme_toggle') }}" title="{{ __('customer_portal.layout.theme_toggle') }}" class="btn btn-sm btn-ghost btn-square">
                                <x-icon name="dark_mode" class="text-base leading-none" data-theme-label />
                            </button>
                            <x-locale-switcher />
                        @endif
                        @if ($portalUser)
                            {{-- Kontomenü: Aktionsmenü der App (details/summary, ohne Skript, L4). --}}
                            <x-action-menu icon="account_circle" :label="$portalUser->name" size="sm" class="max-w-56">
                                @if ($portalCustomer)
                                    <p class="wd-menu-heading truncate">{{ $portalCustomer->name }}</p>
                                @endif
                                <x-icon-btn icon="person" size="sm" :href="route('customer.profile.show')" show-label>{{ __('Profil') }}</x-icon-btn>
                                @if ($portalPreviewState === null)
                                    <x-icon-btn icon="shield" size="sm" :href="route('customer.2fa.show')" show-label>{{ __('Sicherheit') }}</x-icon-btn>
                                    <x-action-form :action="route('customer.logout')" method="POST">
                                        <x-icon-btn icon="logout" size="sm" type="submit" show-label>{{ __('Abmelden') }}</x-icon-btn>
                                    </x-action-form>
                                @else
                                    <x-action-form :action="route('customer.preview.stop')" method="POST">
                                        <x-icon-btn icon="visibility_off" size="sm" type="submit" show-label>{{ __('customer_portal.preview.stop') }}</x-icon-btn>
                                    </x-action-form>
                                @endif
                            </x-action-menu>
                        @endif
                    </div>
                </div>
                @if ($portalNav !== [])
                    {{-- Bereiche ab md als Leiste mit Symbolen und aktivem Eintrag; darunter im Drawer. --}}
                    <nav class="mx-auto hidden w-full max-w-6xl flex-wrap gap-x-1 gap-y-0.5 px-2 pb-2 text-sm md:flex" aria-label="{{ __('Hauptnavigation') }}">
                        @foreach ($portalNav as $item)
                            @php($active = PortalNavigation::isActive($item))
                            <a href="{{ $navLink($item) }}"
                               @class(['flex items-center gap-1.5 rounded-field px-2.5 py-1.5 transition hover:bg-base-200', 'bg-base-200 font-semibold text-primary' => $active])
                               @if ($active) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" class="text-base" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif
            </header>
            <main id="main-content" class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
                {{-- Zentrale Flashes (I4, Gegenstück zu layouts/app): Portal-Views
                     rendern success/error/warning/info nicht erneut. --}}
                @if (session('status'))
                    <div role="status" class="alert alert-info mb-4">{{ session('status') }}</div>
                @endif
                @if (session('success'))
                    <div role="status" class="alert alert-success mb-4 text-sm">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div role="alert" class="alert alert-error mb-4 text-sm">{{ session('error') }}</div>
                @endif
                @if (session('warning'))
                    <div role="alert" class="alert alert-warning mb-4 text-sm">{{ session('warning') }}</div>
                @endif
                @if (session('info'))
                    <div role="status" class="alert alert-info mb-4 text-sm">{{ session('info') }}</div>
                @endif
                {{ $slot ?? '' }}
                @yield('content')

                {{-- Stehendes Pagination-Panel: <x-pagination standing> pusht hierher
                     (App-Konvention, Gegenstück zu layouts/app). Ohne Pagination wird
                     nichts gepusht → kein Leerraum. --}}
                @stack('page-footer')
            </main>
            <footer class="border-t border-base-300 bg-base-100">
                <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-2 px-4 py-3 text-xs text-base-content/70 sm:flex-row">
                    <div class="text-center sm:text-left"><x-footer-copyright /></div>
                    <nav class="flex flex-wrap items-center justify-center gap-4" aria-label="{{ __('customer_portal.layout.legal') }}">
                        <a href="{{ route('legal.imprint') }}" class="transition hover:text-base-content">{{ __('Impressum') }}</a>
                        <a href="{{ route('legal.privacy') }}" class="transition hover:text-base-content">{{ __('Datenschutz') }}</a>
                        <a href="{{ route('legal.accessibility') }}" class="transition hover:text-base-content">{{ __('Barrierefreiheit') }}</a>
                        @if ($portalUser)
                            <a href="{{ route('customer.help.index') }}" class="transition hover:text-base-content">{{ __('customer_help.nav') }}</a>
                        @endif
                    </nav>
                </div>
            </footer>
        </div>
        @if ($portalNav !== [])
            <div class="drawer-side z-50">
                <label for="portal-nav-drawer" class="drawer-overlay" aria-label="{{ __('customer_portal.layout.menu_close') }}"></label>
                <nav class="min-h-full w-72 bg-base-100 p-3" aria-label="{{ __('customer_portal.layout.areas') }}">
                    <div class="mb-2 flex items-center justify-between gap-2 px-2">
                        <span class="truncate font-semibold">{{ $brandName }}</span>
                        <label for="portal-nav-drawer" class="btn btn-ghost btn-sm btn-square" title="{{ __('customer_portal.layout.menu_close') }}" aria-label="{{ __('customer_portal.layout.menu_close') }}">
                            <x-icon name="close" class="text-base" />
                        </label>
                    </div>
                    <ul class="menu w-full p-0">
                        @foreach ($portalNav as $item)
                            @php($active = PortalNavigation::isActive($item))
                            <li>
                                <a href="{{ $navLink($item) }}" @class(['menu-active' => $active]) @if ($active) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" class="text-base" />
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        @endif
    </div>
</body>
</html>
