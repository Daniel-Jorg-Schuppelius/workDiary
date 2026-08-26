{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sms-opt-in.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Selbstverwaltung der Alarm-SMS (Feature 147, MVP-730). Eigene Seite statt
  Block im Profil-Dialog: die Bestätigung braucht eigene Formulare, und ein
  Formular lässt sich nicht in ein anderes schachteln.
--}}
@extends('layouts.app')

@section('title', __('sms.section'))
@section('nav-title', __('sms.section'))

@section('content')
<x-index-page
    :subtitle="__('sms.opt_in_hint')"
    :badge="$active ? __('sms.status_active') : __('sms.status_inactive')"
    :badge-tone="$active ? 'success' : 'neutral'"
>
    <x-validation-errors first />

    <x-card>
        @if (! $hasGateway)
            <p class="text-sm text-base-content/70">{{ __('sms.no_gateway') }}</p>
        @elseif (! $hasMobile)
            <p class="text-sm text-base-content/70">{{ __('sms.no_mobile') }}</p>
        @elseif ($active)
            <form method="POST" action="{{ route('account.sms.destroy') }}">
                @csrf
                @method('DELETE')
                <x-button type="submit" tone="ghost" size="sm" icon="notifications_off">{{ __('sms.revoke') }}</x-button>
            </form>
        @else
            <div class="flex flex-wrap items-end gap-4">
                <form method="POST" action="{{ route('account.sms.start') }}">
                    @csrf
                    <x-button type="submit" tone="primary" size="sm" icon="sms">{{ __('sms.send_code') }}</x-button>
                </form>

                <form method="POST" action="{{ route('account.sms.confirm') }}" class="flex items-end gap-2">
                    @csrf
                    <x-input-field name="code" :label="__('sms.code')" inputmode="numeric" autocomplete="one-time-code" />
                    <x-button type="submit" tone="ghost" size="sm" icon="check">{{ __('sms.confirm') }}</x-button>
                </form>
            </div>
        @endif
    </x-card>
</x-index-page>
@endsection
