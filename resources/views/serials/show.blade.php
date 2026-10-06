{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $serial->serial_no . ' — ' . __('inventory.serial.title'))
@section('nav-title', __('inventory.serial.title'))

@php /** @var \App\Models\Inventory\StockSerial $serial */ @endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$serial->serial_no">
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge>{{ $serial->status->label() }}</x-status-badge>
                <x-status-badge>{{ $serial->source->label() }}</x-status-badge>
            </div>
            @if ($canManage)
                <x-slot:actions>
                    <x-icon-btn icon="label" size="sm" tone="ghost" show-label
                                :href="route('inventory.labels.serial', $serial)"
                                target="_blank">{{ __('Etikett drucken') }}</x-icon-btn>
                    @if ($serial->status->value === 'blocked')
                        <form method="POST" action="{{ route('serials.unblock', $serial) }}">@csrf
                            <x-icon-btn icon="lock_open" size="sm" type="submit" show-label>{{ __('inventory.serial.action.unblock') }}</x-icon-btn>
                        </form>
                    @elseif (! $serial->status->isTerminal())
                        <form method="POST" action="{{ route('serials.block', $serial) }}" class="flex items-center gap-1">@csrf
                            <input aria-label="{{ __('inventory.serial.field.reason') }}" name="reason" placeholder="{{ __('inventory.serial.field.reason') }}" class="input input-xs input-bordered w-32">
                            <x-icon-btn icon="block" tone="warning" size="sm" type="submit" :label="__('inventory.serial.action.block')" />
                        </form>
                    @endif
                    @unless ($serial->status->isTerminal())
                        <x-action-form :action="route('serials.scrap', $serial)" :confirm="__('inventory.serial.action.scrap').'?'">
                            <x-icon-btn placement="danger" icon="delete_forever" tone="error" size="sm" type="submit" :label="__('inventory.serial.action.scrap')" />
                        </x-action-form>
                    @endunless
                </x-slot:actions>
            @endif
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($serial->blocked_reason)
        <x-card>
            <div role="alert" class="alert alert-warning text-sm">{{ $serial->blocked_reason }}</div>
        </x-card>
    @endif

    <x-card>
        <x-detail-grid layout="cells">
            <x-detail-grid.row :label="__('inventory.serial.field.article')">{{ $serial->article?->name }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inventory.serial.field.variant')">{{ $serial->variant?->name ?? $serial->variant?->option_signature ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inventory.serial.field.warehouse')">{{ $serial->warehouse?->name ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inventory.serial.field.customer')">{{ $serial->customer?->name ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inventory.serial.field.order')" class="font-mono">{{ $serial->manufacturingOrder?->number ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inventory.serial.field.shipped_at')">{{ $serial->shipped_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
        </x-detail-grid>
    </x-card>
</x-page-shell>
@endsection
