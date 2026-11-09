{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.public')

@section('title', __('legacy_support.portal.order_title', ['number' => $order->id]))
@section('header-action')
    @include('legacy.support.portal._logout')
@endsection

@section('content')
    @include('legacy.support.portal._frame', ['customer' => $customer, 'active' => 'orders'])

    <div class="mx-auto max-w-3xl rounded-box border border-base-300 bg-base-100 p-5 shadow-xs">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 class="font-['Space_Grotesk'] text-xl font-bold">{{ __('legacy_support.portal.order_title', ['number' => $order->id]) }}</h2>
            <x-status-badge :tone="$order->customerStatusTone()" size="md">{{ $order->customerStatusLabel() }}</x-status-badge>
        </div>

        <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <x-info-tile :label="__('legacy_support.field.date')">{{ \App\Legacy\Support\LegacyClock::format($order->datum) ?? '—' }}</x-info-tile>
            <x-info-tile :label="__('legacy_support.field.appointment')">{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) ?? __('legacy_support.portal.appointment_open') }}</x-info-tile>
            <x-info-tile :label="__('legacy_support.field.name')">{{ $order->kname !== '' ? $order->kname : '—' }}</x-info-tile>
            <x-info-tile :label="__('legacy_support.field.phone')">{{ $order->telefon !== '' ? $order->telefon : '—' }}</x-info-tile>
            <x-info-tile :label="__('legacy_support.field.object')" class="sm:col-span-2">{{ $order->objekt !== '' ? $order->objekt : '—' }}</x-info-tile>
        </div>

        <div class="mt-4">
            <p class="mb-1 text-xs text-muted">{{ __('legacy_support.field.order_text') }}</p>
            <div class="whitespace-pre-wrap rounded-box border border-base-300 bg-base-200/60 p-3 leading-relaxed">{{ $order->auftrag !== '' ? $order->auftrag : '—' }}</div>
        </div>

        <div class="mt-4 rounded-box border border-info/30 bg-info/10 p-3">
            <p class="mb-1 text-xs text-muted">{{ __('legacy_support.portal.replies') }}</p>
            <div class="whitespace-pre-wrap leading-relaxed">{{ $order->antwort !== '' ? $order->antwort : __('legacy_support.portal.no_replies') }}</div>
        </div>

        <div class="mt-5">
            <x-icon-btn icon="arrow_back" size="sm" :href="route('legacy.portal.orders.index')" show-label>{{ __('legacy_support.portal.back_to_list') }}</x-icon-btn>
        </div>
    </div>
@endsection
