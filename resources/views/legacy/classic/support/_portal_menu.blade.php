{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_menu.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Menü der Kunden wie im Altportal (kunde/menue.php). Erwartet: $customer --}}
@php
    $writeEnabled = (bool) config('app.legacy_write_enabled');
    $menu = array_values(array_filter([
        $writeEnabled ? ['href' => route('legacy.portal.orders.create'), 'label' => __('legacy_support.classic.menu.new_order'), 'active' => request()->routeIs('legacy.portal.orders.create')] : null,
        ['href' => route('legacy.portal.orders.index'), 'label' => __('legacy_support.classic.menu.orders'), 'active' => request()->routeIs('legacy.portal.orders.index', 'legacy.portal.orders.show')],
        $writeEnabled ? ['href' => route('legacy.portal.password.edit'), 'label' => __('legacy_support.classic.menu.password'), 'title' => __('legacy_support.classic.menu.password_title'), 'active' => request()->routeIs('legacy.portal.password.edit')] : null,
        ['href' => route('legacy.portal.logout'), 'label' => __('legacy_support.classic.menu.logout'), 'title' => __('legacy_support.classic.menu.logout_title'), 'post' => true],
    ]));
@endphp
<h2>{{ __('legacy_support.classic.portal') }}</h2>
<p class="kp-userline">{{ __('legacy_support.classic.logged_in_as', ['name' => $customer->kunde]) }}</p>
@include('legacy.classic._menu', ['label' => __('legacy_support.classic.portal'), 'rows' => [$menu]])
@unless ($writeEnabled)
    <p class="kp-alert">{{ __('legacy_support.portal.read_only') }}</p>
@endunless
@include('legacy.classic._flash')
