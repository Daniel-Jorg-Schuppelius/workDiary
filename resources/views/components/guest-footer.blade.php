{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : guest-footer.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Fußzeile aller Gastseiten nach der App-Footer-Anatomie (layouts/app): fix
    am unteren Rand, h-12, kompakt und mobil gestapelt. Statt Version und
    Build-Hash (nur angemeldet relevant) die Pflicht-Links zu den Rechtstexten.
--}}
@php
    $legalLinks = [
        'legal.imprint' => __('Impressum'),
        'legal.privacy' => __('Datenschutz'),
        'legal.accessibility' => __('Barrierefreiheit'),
    ];
@endphp
<footer class="fixed inset-x-0 bottom-0 z-50 h-12 border-t border-base-300 bg-base-100 shadow-xs">
    <div class="mx-auto flex h-full w-full max-w-screen-2xl flex-col items-center justify-center gap-0 px-4 text-center text-[0.65rem] leading-tight text-base-content/70 sm:flex-row sm:gap-4 sm:text-xs xl:px-8 2xl:px-12">
        <div class="max-w-full"><x-footer-copyright /></div>
        <nav class="flex items-center gap-4" aria-label="{{ __('Rechtliches') }}">
            @foreach ($legalLinks as $routeName => $label)
                <a href="{{ route($routeName) }}"
                   @class(['transition hover:text-base-content', 'font-semibold text-base-content' => request()->routeIs($routeName)])
                   @if (request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</footer>
