{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duty-form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bereitschaft oder Notdienst anlegen und ändern wie im Altportal, das Datum mit dem Datumswähler. Erwartet: $item, $users, $isEdit, $kind --}}
@php
    $cell = $kind === 'oncall' ? 'bereit' : 'notdnst';
    $tab = $kind === 'oncall' ? 'bereitschaft' : 'notdienst';
    $userKey = fn ($id) => \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $id);
    $selectedUser = (string) old('user', $item !== null ? $userKey($item->user) : '');
@endphp
@extends('legacy.classic.shell')
@section('title', __('legacy_classic.duty.' . $kind . '.title'))
@section('bodyClass', 'tb')

@section('content')
    @include('legacy.classic._flash')
    <h1>{{ __('legacy_classic.duty.' . $kind . '.title') }}</h1>

    <form method="POST" action="{{ $isEdit ? route('legacy.' . $kind . '.update', $item) : route('legacy.' . $kind . '.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        <table class="kp-msg">
            <tr><td colspan="2" class="{{ $cell }} mitte">{{ __('legacy_classic.duty.' . $kind . ($isEdit ? '.edit_heading' : '.new_heading')) }}</td></tr>
            <tr>
                <td class="rechts"><label for="tb-duty-user">{{ __('legacy_classic.label.employee') }}</label></td>
                <td class="ohne">
                    <select id="tb-duty-user" name="user">
                        <option value="">{{ __('legacy_classic.please_choose') }}</option>
                        @foreach ($users as $legacyUser)
                            <option value="{{ $userKey($legacyUser->id) }}" @selected($selectedUser === $userKey($legacyUser->id))>{{ $legacyUser->uname }}</option>
                        @endforeach
                    </select>
                </td>
            </tr>
            @foreach (['von' => 'from', 'bis' => 'until'] as $field => $labelKey)
                <tr>
                    <td class="rechts"><label for="tb-duty-{{ $field }}">{{ __('legacy_classic.label.' . $labelKey) }}</label></td>
                    <td class="ohne"><input id="tb-duty-{{ $field }}" type="date" name="{{ $field }}" value="{{ old($field, $item?->{$field}?->format('Y-m-d')) }}"></td>
                </tr>
            @endforeach
            <tr>
                <td class="ohne">&nbsp;</td>
                <td class="ohne"><button type="submit">{{ $isEdit ? __('legacy_classic.action.change') : __('legacy_classic.action.create') }}</button></td>
            </tr>
        </table>
    </form>
    <p>&raquo; <a href="{{ route('legacy.diary.index', ['tab' => $tab]) }}">{{ __('legacy_classic.duty.' . $kind . '.back') }}</a> &laquo;</p>
@endsection
