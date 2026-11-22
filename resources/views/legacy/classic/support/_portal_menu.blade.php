{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_menu.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Menü der Kunden wie im Altportal (kunde/menue.php). Erwartet: $customer --}}
@php $writeEnabled = (bool) config('app.legacy_write_enabled'); @endphp
<h2>{{ __('legacy_support.classic.portal') }}</h2>
<p>{{ __('legacy_support.classic.logged_in_as', ['name' => $customer->kunde]) }}</p>
<nav aria-label="{{ __('legacy_support.classic.portal') }}">
    <table class="hmenue">
        <tr>
            @if ($writeEnabled)
                <td><a href="{{ route('legacy.portal.orders.create') }}">&nbsp;{{ __('legacy_support.classic.menu.new_order') }}&nbsp;</a></td>
            @endif
            <td><a href="{{ route('legacy.portal.orders.index') }}">&nbsp;{{ __('legacy_support.classic.menu.orders') }}&nbsp;</a></td>
            @if ($writeEnabled)
                <td><a href="{{ route('legacy.portal.password.edit') }}" title="{{ __('legacy_support.classic.menu.password_title') }}">&nbsp;{{ __('legacy_support.classic.menu.password') }}&nbsp;</a></td>
            @endif
            <td>
                <form method="POST" action="{{ route('legacy.portal.logout') }}">
                    @csrf
                    <button type="submit" class="kp-linkbutton" title="{{ __('legacy_support.classic.menu.logout_title') }}">&nbsp;{{ __('legacy_support.classic.menu.logout') }}&nbsp;</button>
                </form>
            </td>
        </tr>
    </table>
</nav>
@unless ($writeEnabled)
    <p class="kp-msg">{{ __('legacy_support.portal.read_only') }}</p>
@endunless
@include('legacy.classic._flash')
