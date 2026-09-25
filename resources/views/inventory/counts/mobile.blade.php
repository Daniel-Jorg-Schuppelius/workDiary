{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : mobile.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zählansicht fürs Handy (MVP-898): fortlaufend scannen, jeder Scan addiert.
     Offline landet der Scan in der Sync-Outbox (inventory.count). --}}
@extends('layouts.app')
@section('title', __('inventory.count_ui.mobile_title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.count_ui.mobile_title'))

@php /** @var \App\Models\Inventory\StockCount $count */ @endphp

@section('content')
<x-page-shell gap="3">
    <x-slot:toolbar>
        <x-page-toolbar :title="$count->warehouse?->name" :subtitle="__('inventory.count_ui.mobile_progress', ['counted' => $countedLines, 'total' => $count->lines->count()])">
            <x-slot:actions>
                <x-icon-btn icon="list" size="sm" :href="route('inventory.counts.show', $count)" :label="__('inventory.count_ui.title')" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($count->status->isOpen())
        <x-card>
            <form method="POST" action="{{ route('inventory.counts.scan-add', $count) }}" class="space-y-3"
                  data-offline-sync="inventory.count" data-sync-payload-count="{{ $count->sqid }}">
                @csrf
                <div class="fieldset"><label for="code" class="fieldset-label">{{ __('inventory.scan.code') }}</label>
                    <input id="code" name="code" autofocus required autocomplete="off" inputmode="text" enterkeyhint="send"
                           class="input input-lg input-bordered w-full font-mono" placeholder="GTIN / SKU / SN / LOT"></div>
                <div class="fieldset"><label for="qty" class="fieldset-label">{{ __('inventory.count_ui.mobile_qty') }}</label>
                    <input id="qty" name="qty" type="number" step="0.0001" min="0.0001" value="1" inputmode="decimal"
                           class="input input-lg input-bordered w-full"></div>
                <x-button type="submit" tone="primary" class="w-full">{{ __('inventory.count_ui.mobile_add') }}</x-button>
            </form>
            <p class="mt-2 text-xs text-muted">{{ __('inventory.count_ui.mobile_hint') }}</p>
        </x-card>
    @else
        <p class="text-sm text-muted">{{ __('inventory.count_ui.closed') }}</p>
    @endif

    @if ($last !== null)
        <x-card :title="__('inventory.count_ui.mobile_last')">
            <div class="flex items-baseline justify-between gap-2">
                <span class="font-medium">{{ $last->variant?->article?->name }} — {{ $last->variant?->name ?? $last->variant?->option_signature }}</span>
                <span class="text-2xl font-semibold tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $last->counted_qty, 4, trimTrailingZeros: true) }}</span>
            </div>
        </x-card>
    @endif
</x-page-shell>
@endsection
