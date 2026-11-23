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
    $menuTab = (string) request()->query('tab', '');
    $onIndex = request()->routeIs('legacy.diary.index');
    $rowOne = array_values(array_filter([
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.week'), 'label' => __('legacy_classic.menu.diary'), 'active' => request()->routeIs('legacy.diary.week', 'legacy.diary.show', 'legacy.diary.create', 'legacy.diary.edit')],
        ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.diary.index'), 'label' => __('legacy_classic.menu.overview'), 'title' => __('legacy_classic.menu.overview_title'), 'active' => $onIndex && ! in_array($menuTab, ['bereitschaft', 'notdienst'], true)],
        $canManage ? ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.users.index'), 'label' => __('legacy_classic.menu.users'), 'title' => __('legacy_classic.menu.users_title'), 'active' => request()->routeIs('legacy.users.*')] : null,
        $canManage && $writeEnabled ? ['href' => route('legacy.archive.prepare'), 'label' => __('legacy_classic.menu.archiving'), 'title' => __('legacy_classic.menu.archiving_title'), 'active' => request()->routeIs('legacy.archive.prepare')] : null,
        $writeEnabled ? ['href' => route('legacy.account.password.edit'), 'label' => __('legacy_classic.menu.password'), 'title' => __('legacy_classic.menu.password_title'), 'active' => request()->routeIs('legacy.account.password.edit')] : null,
        ['href' => route('logout'), 'label' => __('legacy_classic.menu.logout'), 'title' => __('legacy_classic.menu.logout_title'), 'post' => true],
    ]));
    $rowTwo = array_values(array_filter([
        $canManage ? ['href' => route('legacy.diary.index', ['tab' => 'bereitschaft']), 'label' => __('legacy_classic.menu.oncall'), 'title' => __('legacy_classic.menu.oncall_title'), 'active' => ($onIndex && $menuTab === 'bereitschaft') || request()->routeIs('legacy.oncall.*')] : null,
        $canManage ? ['href' => route('legacy.diary.index', ['tab' => 'notdienst']), 'label' => __('legacy_classic.menu.notdienst'), 'title' => __('legacy_classic.menu.notdienst_title'), 'active' => ($onIndex && $menuTab === 'notdienst') || request()->routeIs('legacy.notdienst.*')] : null,
        $canManage ? ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.archive.week'), 'label' => __('legacy_classic.menu.archive'), 'title' => __('legacy_classic.menu.archive_title'), 'active' => request()->routeIs('legacy.archive.week', 'legacy.archive.show')] : null,
        $canManage ? ['href' => \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.orders.index'), 'label' => __('legacy_classic.menu.support')] : null,
    ]));
@endphp
<p class="kp-userline">{{ __('legacy_classic.logged_in_as', ['name' => $menuUser?->name]) }}</p>
@include('legacy.classic._menu', ['label' => __('legacy_classic.diary_title'), 'rows' => [$rowOne, $rowTwo]])
