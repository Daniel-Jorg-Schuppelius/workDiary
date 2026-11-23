{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mitarbeiterverwaltung wie im Altportal (muser.php). Erwartet: $users, $sort, $dir --}}
@php
    $writeEnabled = (bool) config('app.legacy_write_enabled');
    $sortParams = ['route' => 'legacy.users.index', 'sort' => $sort, 'dir' => $dir];
@endphp
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.users.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic.diary._menu')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.users.title') }}</h1>

    @if ($writeEnabled)
        <p>&raquo; <a href="{{ route('legacy.users.create') }}">{{ __('legacy_classic.users.new') }}</a> &laquo;</p>
    @endif
    <p>{{ __('legacy_classic.users.existing') }}</p>
    <table>
        <tr>
            @include('legacy.classic._sort', [...$sortParams, 'key' => 'id', 'label' => __('legacy_classic.col.id')])
            @include('legacy.classic._sort', [...$sortParams, 'key' => 'uname', 'label' => __('legacy_classic.col.username')])
            @if ($writeEnabled)
                <th colspan="2">{{ __('legacy_classic.col.option') }}</th>
            @endif
        </tr>
        @forelse ($users as $legacyUser)
            <tr>
                <td class="mitte">{{ $legacyUser->id }}</td>
                <td>{{ $legacyUser->uname }}</td>
                @if ($writeEnabled)
                    <td class="option-edit"><a href="{{ route('legacy.users.edit', $legacyUser) }}">{{ __('legacy_classic.action.edit') }}</a></td>
                    <td class="option-delete"><a href="{{ route('legacy.users.edit', [$legacyUser, 'delete' => 1]) }}">{{ __('legacy_classic.action.delete') }}</a></td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $writeEnabled ? 4 : 2 }}" class="mitte">{{ __('legacy_classic.none') }}</td></tr>
        @endforelse
    </table>
@endsection
