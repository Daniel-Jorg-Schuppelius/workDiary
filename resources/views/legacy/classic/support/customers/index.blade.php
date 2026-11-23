{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenverwaltung im Aussehen des Altportals (admin/muser.php). Erwartet: $customers, $sort, $dir, $writeEnabled --}}
@extends('legacy.classic.shell')
@section('title', __('legacy_support.nav.customers'))

@section('content')
    @include('legacy.classic.support._office_menu')
    <h2>{{ __('legacy_support.classic.portal') }}</h2>
    @include('legacy.classic._flash')
    <p>{{ __('legacy_support.customers.portal_address') }} <a href="{{ route('legacy.portal.login') }}">{{ route('legacy.portal.login') }}</a></p>
    @if ($writeEnabled)
        <p><a href="{{ route('legacy.support.customers.create') }}">{{ __('legacy_support.classic.customers.new_user') }}</a></p>
    @else
        <p class="kp-alert">{{ __('legacy_support.read_only') }}</p>
    @endif

    <div class="kp-scroll">
        <table class="kp-medium kp-list">
            <thead>
                <tr>
                    @include('legacy.classic._sort', ['route' => 'legacy.support.customers.index', 'key' => 'kunde', 'label' => __('legacy_support.classic.col.firm')])
                    @include('legacy.classic._sort', ['route' => 'legacy.support.customers.index', 'key' => 'usern', 'label' => __('legacy_support.classic.col.username')])
                    <th>{{ __('legacy_support.classic.col.password') }}</th>
                    <th colspan="3">{{ __('legacy_support.classic.col.option') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->kunde }}</td>
                        <td>{{ $customer->usern }}</td>
                        <td>********</td>
                        <td class="kp-icon">
                            <a href="{{ route('legacy.support.customers.show', $customer) }}" title="{{ __('legacy_support.classic.action.view') }}" aria-label="{{ __('legacy_support.classic.action.view') }}: {{ $customer->kunde }}">@include('legacy.classic.support._box', ['kind' => 'view'])</a>
                        </td>
                        @if ($writeEnabled)
                            <td class="kp-icon">
                                <a href="{{ route('legacy.support.customers.edit', $customer) }}" title="{{ __('legacy_support.classic.action.change') }}" aria-label="{{ __('legacy_support.classic.action.change') }}: {{ $customer->kunde }}">@include('legacy.classic.support._box', ['kind' => 'edit'])</a>
                            </td>
                            <td class="kp-icon">
                                <a href="{{ route('legacy.support.customers.show', [$customer, 'delete' => 1]) }}" title="{{ __('legacy_support.classic.action.delete') }}" aria-label="{{ __('legacy_support.classic.action.delete') }}: {{ $customer->kunde }}">@include('legacy.classic.support._box', ['kind' => 'delete'])</a>
                            </td>
                        @else
                            <td></td><td></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="kp-center">{{ __('legacy_support.customers.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
