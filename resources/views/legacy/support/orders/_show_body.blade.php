{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Support-Auftrag, Bürosicht mit internen Feldern. Erwartet: $order (LegacySupportOrder|LegacySupportArchivedOrder) --}}
@php
    $appointment = $order->appointmentAt();
@endphp

<div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
    <x-status-badge :tone="$order->statusTone()" size="md">{{ $order->statusLabel() }}</x-status-badge>
    @if ($order->isOverdue())
        <x-status-badge tone="error" size="md" icon="schedule">{{ __('legacy_support.status.overdue') }}</x-status-badge>
    @endif
    <span class="ml-auto text-base-content/70">{{ __('legacy_support.field.number') }} {{ $order->id }}</span>
</div>

<div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
    <x-info-tile :label="__('legacy_support.field.customer')">{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</x-info-tile>
    <x-info-tile :label="__('legacy_support.field.date')">{{ \App\Legacy\Support\LegacyClock::format($order->datum) ?? '—' }}</x-info-tile>
    <x-info-tile :label="__('legacy_support.field.orderer')">{{ $order->kname !== '' ? $order->kname : '—' }}</x-info-tile>
    <x-info-tile :label="__('legacy_support.field.phone')">{{ $order->telefon !== '' ? $order->telefon : '—' }}</x-info-tile>
    <x-info-tile :label="__('legacy_support.field.object')" class="sm:col-span-2">{{ $order->objekt !== '' ? $order->objekt : '—' }}</x-info-tile>
</div>

<div class="mt-4">
    <p class="mb-1 text-xs text-muted">{{ __('legacy_support.field.order_text') }}</p>
    <div class="whitespace-pre-wrap rounded-box border border-base-300 bg-base-100 p-3 leading-relaxed">{{ $order->auftrag !== '' ? $order->auftrag : '—' }}</div>
</div>

<div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
    <x-info-tile :label="__('legacy_support.field.appointment')">
        <span @class(['font-semibold text-error' => $order->isOverdue()])>{{ \App\Legacy\Support\LegacyClock::format($appointment) ?? __('legacy_support.no_appointment') }}</span>
    </x-info-tile>
    <x-info-tile :label="__('legacy_support.field.mechanic')">{{ $order->wer !== '' ? $order->wer : __('legacy_support.no_mechanic') }}</x-info-tile>
    <x-info-tile :label="__('legacy_support.field.duration')">{{ $order->dauer !== '' ? $order->dauer : '—' }}</x-info-tile>
</div>

<div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
    <div class="rounded-box border border-info/30 bg-info/10 p-3">
        <p class="mb-1 text-xs text-muted">{{ __('legacy_support.field.reply') }}</p>
        <div class="whitespace-pre-wrap leading-relaxed">{{ $order->antwort !== '' ? $order->antwort : '—' }}</div>
    </div>
    <div class="rounded-box border border-warning/30 bg-warning/10 p-3">
        <p class="mb-1 text-xs text-muted">{{ __('legacy_support.field.internal_note') }}</p>
        <div class="whitespace-pre-wrap leading-relaxed">{{ $order->intern !== '' ? $order->intern : '—' }}</div>
    </div>
</div>
