{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : kiosk.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kiosk-Modus (MVP-800): Tablet als Stempelterminal. Variablen: $terminal, $ingestUrl --}}
@extends('layouts.guest')

@section('title', __('terminal.kiosk.title'))
@section('headline', $terminal->name)
@section('intro', __('terminal.kiosk.intro'))
@section('header-action')
@endsection

@section('content')
    @php
        $messages = [
            'clocked_in' => __('terminal.kiosk.status.clocked_in'),
            'clocked_out' => __('terminal.kiosk.status.clocked_out'),
            'break_started' => __('terminal.kiosk.status.break_started'),
            'break_ended' => __('terminal.kiosk.status.break_ended'),
            'noop' => __('terminal.kiosk.status.noop'),
            'skipped' => __('terminal.kiosk.status.skipped'),
            'unknown_badge' => __('terminal.kiosk.status.unknown_badge'),
            'rejected' => __('terminal.kiosk.status.rejected'),
            'invalid_token' => __('terminal.kiosk.status.invalid_token'),
            'invalid_pin' => __('terminal.kiosk.status.invalid_pin'),
            'tenant_blocked' => __('terminal.kiosk.status.unavailable'),
            'maintenance' => __('terminal.kiosk.status.unavailable'),
            'network' => __('terminal.kiosk.status.network'),
            'nfc_unavailable' => __('terminal.kiosk.status.nfc_unavailable'),
            'error' => __('terminal.kiosk.status.error'),
            'flex_balance' => __('terminal.kiosk.flex_balance'),
            'nfc_active' => __('terminal.kiosk.nfc_active'),
        ];
    @endphp
    <x-card data-kiosk
         data-ingest-url="{{ $ingestUrl }}"
         data-messages="{{ json_encode($messages, JSON_UNESCAPED_UNICODE) }}" padding="p-6">
        <div class="mb-4 text-center font-mono text-5xl font-semibold tabular-nums" data-kiosk-clock aria-hidden="true">--:--</div>

        <div class="join mb-4 w-full" role="group" aria-label="{{ __('terminal.kiosk.mode') }}">
            <button type="button" class="btn join-item flex-1 btn-primary" data-kiosk-mode-button="work" aria-pressed="true">{{ __('terminal.kiosk.mode_work') }}</button>
            <button type="button" class="btn join-item flex-1" data-kiosk-mode-button="break" aria-pressed="false">{{ __('terminal.kiosk.mode_break') }}</button>
        </div>

        <form data-kiosk-form autocomplete="off">
            <label for="kiosk-badge" class="mb-1 block text-sm font-medium">{{ __('terminal.kiosk.badge_label') }}</label>
            <input id="kiosk-badge" type="password" inputmode="none" autocomplete="off"
                   class="input input-bordered input-lg w-full text-center" data-kiosk-input>
        </form>

        {{-- Ausweis vergessen (MVP-803): Personalnummer + PIN. --}}
        <details class="mt-3" data-kiosk-pin-toggle>
            <summary class="cursor-pointer text-sm">{{ __('terminal.kiosk.pin_toggle') }}</summary>
            <form class="mt-2 grid gap-2" data-kiosk-pin-form autocomplete="off">
                <label for="kiosk-personnel" class="text-sm font-medium">{{ __('terminal.pin.field.personnel_number') }}</label>
                <input id="kiosk-personnel" type="text" inputmode="numeric" autocomplete="off" class="input input-bordered" data-kiosk-personnel>
                <label for="kiosk-pin" class="text-sm font-medium">{{ __('terminal.pin.field.pin') }}</label>
                <input id="kiosk-pin" type="password" inputmode="numeric" autocomplete="off" class="input input-bordered" data-kiosk-pin>
                <button type="submit" class="btn btn-primary">{{ __('terminal.kiosk.pin_submit') }}</button>
            </form>
        </details>

        <button type="button" class="btn btn-outline mt-3 hidden w-full" data-kiosk-nfc>{{ __('terminal.kiosk.nfc_start') }}</button>

        <div class="mt-4 min-h-16" role="status" aria-live="polite" data-kiosk-result></div>
    </x-card>
@endsection

@section('after-body')
    @vite('resources/js/kiosk.js')
@endsection
