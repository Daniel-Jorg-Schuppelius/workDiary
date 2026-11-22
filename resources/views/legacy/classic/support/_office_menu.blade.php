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
    $menu = array_filter([
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.orders.index'), 'label' => __('legacy_support.classic.menu.orders')],
        $writeEnabled ? ['href' => route('legacy.support.orders.create'), 'label' => __('legacy_support.classic.menu.create')] : null,
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.customers.index'), 'label' => __('legacy_support.classic.menu.customers'), 'title' => __('legacy_support.classic.menu.customers_title')],
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.archive.index'), 'label' => __('legacy_support.classic.menu.archive')],
        $writeEnabled ? ['href' => route('legacy.support.archive.prepare'), 'label' => __('legacy_support.classic.menu.archiving'), 'title' => __('legacy_support.classic.menu.archiving_title')] : null,
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.week'), 'label' => __('legacy_classic.menu.diary')],
        ['href' => route('home'), 'label' => __('legacy_support.classic.menu.app', ['app' => config('app.name', 'WorkDiary')])],
    ]);
@endphp
<nav aria-label="{{ __('legacy_support.classic.portal') }}">
    <table class="hmenue">
        <tr>
            @foreach ($menu as $item)
                <td><a href="{{ $item['href'] }}" @isset($item['title']) title="{{ $item['title'] }}" @endisset>&nbsp;{{ $item['label'] }}&nbsp;</a></td>
            @endforeach
        </tr>
    </table>
</nav>
