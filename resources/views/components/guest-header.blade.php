{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : guest-header.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Kopfzeile aller Gastseiten nach der App-Header-Anatomie (layouts/app):
    sticky, opakes bg-base-100, border-b + shadow-xs, min-h-14, Logo h-9,
    Aktionen rechts als freie Buttons (keine Pillen-Box).

    Slots:
      nav      Sprunglinks, ab lg mittig
      actions  ersetzt „Anmelden"; leer übergeben = kein Button
--}}
@php
    $brandName = isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary');
    $brandLogo = isset($branding) && $branding ? $branding->logoUrl() : asset('img/logo/workdiary-logo-512.png');
@endphp
<header class="sticky top-0 z-50 border-b border-base-300 bg-base-100 shadow-xs">
    <div class="flex min-h-14 w-full items-center justify-between gap-4 px-3 py-2 xl:px-4">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2">
            @if ($brandLogo)
                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-9 w-auto max-w-40 shrink-0 object-contain">
            @else
                <span class="font-['Space_Grotesk'] text-lg font-bold tracking-tight text-base-content">{{ $brandName }}</span>
            @endif
        </a>
        @isset($nav)
            <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Seitenbereiche') }}">
                {{ $nav }}
            </nav>
        @endisset
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" data-theme-toggle aria-label="{{ __('Farbschema wechseln') }}" title="{{ __('Farbschema wechseln') }}" class="btn btn-sm btn-ghost btn-square">
                <x-icon name="dark_mode" class="text-base leading-none" data-theme-label />
            </button>
            <x-locale-switcher />
            @isset($actions)
                {{ $actions }}
            @else
                <x-icon-btn icon="login" tone="primary" size="sm" :href="route('login')" show-label>{{ __('Anmelden') }}</x-icon-btn>
            @endisset
        </div>
    </div>
</header>
