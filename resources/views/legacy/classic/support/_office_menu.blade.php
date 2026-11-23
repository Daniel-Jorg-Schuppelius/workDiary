{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _office_menu.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Menü der Bürokonten wie im Altportal (admin/menue.php); die Listen behalten Jahr und Sortierung. --}}
@php
    $writeEnabled = (bool) config('app.legacy_write_enabled');
    $menu = array_values(array_filter([
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.orders.index'), 'label' => __('legacy_support.classic.menu.orders'), 'active' => request()->routeIs('legacy.support.orders.index', 'legacy.support.orders.show', 'legacy.support.orders.edit')],
        $writeEnabled ? ['href' => route('legacy.support.orders.create'), 'label' => __('legacy_support.classic.menu.create'), 'active' => request()->routeIs('legacy.support.orders.create')] : null,
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.customers.index'), 'label' => __('legacy_support.classic.menu.customers'), 'title' => __('legacy_support.classic.menu.customers_title'), 'active' => request()->routeIs('legacy.support.customers.*')],
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.archive.index'), 'label' => __('legacy_support.classic.menu.archive'), 'active' => request()->routeIs('legacy.support.archive.index', 'legacy.support.archive.show')],
        $writeEnabled ? ['href' => route('legacy.support.archive.prepare'), 'label' => __('legacy_support.classic.menu.archiving'), 'title' => __('legacy_support.classic.menu.archiving_title'), 'active' => request()->routeIs('legacy.support.archive.prepare')] : null,
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.week'), 'label' => __('legacy_classic.menu.diary')],
    ]));
@endphp
@include('legacy.classic._menu', ['label' => __('legacy_support.classic.portal'), 'rows' => [$menu]])
