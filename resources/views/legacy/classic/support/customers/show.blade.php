{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenzugang ansehen bzw. Rückfrage vor dem Löschen (admin/listuser.php, deluser.php). Erwartet: $customer, $confirmDelete, $writeEnabled --}}
@extends('legacy.classic.shell')
@section('title', $customer->kunde)

@section('content')
    @include('legacy.classic.support._office_menu')
    <p><a href="{{ \App\Http\Middleware\RememberListUrl::urlFor('legacy.support.customers.index') }}">{{ __('legacy_support.classic.to_list') }}</a></p>
    @include('legacy.classic._flash')

    <table class="eintrag kp-medium">
        <tr><th>{{ __('legacy_support.classic.col.firm') }}:</th><th>{{ $customer->kunde }}</th></tr>
        <tr><th>{{ __('legacy_support.classic.col.username') }}:</th><th>{{ $customer->usern }}</th></tr>
        <tr><th>{{ __('legacy_support.classic.col.password') }}:</th><th>********</th></tr>
        <tr><th>{{ __('legacy_support.customers.open_orders') }}:</th><th>{{ $customer->orders_count }}</th></tr>
        <tr><th>{{ __('legacy_support.customers.archived_orders') }}:</th><th>{{ $customer->archived_orders_count }}</th></tr>
        @if ($confirmDelete && $writeEnabled)
            <tr>
                <th>{{ __('legacy_support.classic.customers.delete_question') }}</th>
                <th>
                    <form method="POST" action="{{ route('legacy.support.customers.destroy', $customer) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="kp-danger">{{ __('legacy_support.classic.yes') }}</button>
                    </form>
                </th>
            </tr>
        @endif
    </table>
@endsection
