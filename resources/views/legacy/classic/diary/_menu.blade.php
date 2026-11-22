{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _menu.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Menü des Online-Tagebuchs wie im Altportal (admin/menue.php); Verwaltung nur für das Büro. --}}
@php
    $menuUser = auth()->user();
    $canManage = \App\Legacy\Support\LegacyRoleResolver::canManage($menuUser);
    $writeEnabled = (bool) config('app.legacy_write_enabled');
    $rowOne = array_filter([
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.week'), 'label' => __('legacy_classic.menu.diary')],
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.index'), 'label' => __('legacy_classic.menu.overview'), 'title' => __('legacy_classic.menu.overview_title')],
        $canManage ? ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.users.index'), 'label' => __('legacy_classic.menu.users'), 'title' => __('legacy_classic.menu.users_title')] : null,
        $canManage && $writeEnabled ? ['href' => route('legacy.archive.prepare'), 'label' => __('legacy_classic.menu.archiving'), 'title' => __('legacy_classic.menu.archiving_title')] : null,
        $writeEnabled ? ['href' => route('legacy.account.password.edit'), 'label' => __('legacy_classic.menu.password'), 'title' => __('legacy_classic.menu.password_title')] : null,
    ]);
    $rowTwo = $canManage ? [
        ['href' => route('legacy.diary.index', ['tab' => 'bereitschaft']), 'label' => __('legacy_classic.menu.oncall'), 'title' => __('legacy_classic.menu.oncall_title')],
        ['href' => route('legacy.diary.index', ['tab' => 'notdienst']), 'label' => __('legacy_classic.menu.notdienst'), 'title' => __('legacy_classic.menu.notdienst_title')],
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.archive.week'), 'label' => __('legacy_classic.menu.archive'), 'title' => __('legacy_classic.menu.archive_title')],
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.orders.index'), 'label' => __('legacy_classic.menu.support')],
    ] : [];
@endphp
<p>{{ __('legacy_classic.logged_in_as', ['name' => $menuUser?->name]) }}</p>
<nav aria-label="{{ __('legacy_classic.diary_title') }}">
    <table class="hmenue">
        <tr>
            @foreach ($rowOne as $item)
                <td><a href="{{ $item['href'] }}" @isset($item['title']) title="{{ $item['title'] }}" @endisset>&nbsp;{{ $item['label'] }}&nbsp;</a></td>
            @endforeach
            <td>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="kp-linkbutton" title="{{ __('legacy_classic.menu.logout_title') }}">&nbsp;{{ __('legacy_classic.menu.logout') }}&nbsp;</button>
                </form>
            </td>
        </tr>
        @if ($rowTwo !== [])
            <tr>
                @foreach ($rowTwo as $item)
                    <td><a href="{{ $item['href'] }}" @isset($item['title']) title="{{ $item['title'] }}" @endisset>&nbsp;{{ $item['label'] }}&nbsp;</a></td>
                @endforeach
            </tr>
        @endif
    </table>
    <p><a href="{{ route('home') }}">{{ __('legacy_classic.menu.app', ['app' => config('app.name', 'WorkDiary')]) }}</a></p>
</nav>
