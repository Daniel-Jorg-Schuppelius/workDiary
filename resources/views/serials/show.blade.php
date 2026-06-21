@extends('layouts.app')
@section('title', $serial->serial_no . ' — ' . __('inventory.serial.title'))
@section('nav-title', __('inventory.serial.title'))

@php /** @var \App\Models\StockSerial $serial */ @endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$serial->serial_no">
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-sm badge-ghost">{{ $serial->status->label() }}</span>
                <span class="badge badge-sm badge-ghost">{{ $serial->source->label() }}</span>
            </div>
            @if ($canManage)
                <x-slot:actions>
                    @if ($serial->status->value === 'blocked')
                        <form method="POST" action="{{ route('serials.unblock', $serial) }}">@csrf
                            <x-icon-btn icon="lock_open" size="sm" type="submit" show-label>{{ __('inventory.serial.action.unblock') }}</x-icon-btn>
                        </form>
                    @elseif (! $serial->status->isTerminal())
                        <form method="POST" action="{{ route('serials.block', $serial) }}" class="flex items-center gap-1">@csrf
                            <input name="reason" placeholder="{{ __('inventory.serial.field.reason') }}" class="input input-xs input-bordered w-32">
                            <x-icon-btn icon="block" tone="warning" size="sm" type="submit" :title="__('inventory.serial.action.block')" />
                        </form>
                    @endif
                    @unless ($serial->status->isTerminal())
                        <x-action-form :action="route('serials.scrap', $serial)" :confirm="__('inventory.serial.action.scrap').'?'">
                            <x-icon-btn icon="delete_forever" tone="error" size="sm" type="submit" :title="__('inventory.serial.action.scrap')" />
                        </x-action-form>
                    @endunless
                </x-slot:actions>
            @endif
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($serial->blocked_reason)
        <x-card>
            <div class="alert alert-warning text-sm">{{ $serial->blocked_reason }}</div>
        </x-card>
    @endif

    <x-card>
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div><dt class="opacity-60">{{ __('inventory.serial.field.article') }}</dt><dd>{{ $serial->article?->name }}</dd></div>
            <div><dt class="opacity-60">{{ __('inventory.serial.field.variant') }}</dt><dd>{{ $serial->variant?->name ?? $serial->variant?->option_signature ?? '—' }}</dd></div>
            <div><dt class="opacity-60">{{ __('inventory.serial.field.warehouse') }}</dt><dd>{{ $serial->warehouse?->name ?? '—' }}</dd></div>
            <div><dt class="opacity-60">{{ __('inventory.serial.field.customer') }}</dt><dd>{{ $serial->customer?->name ?? '—' }}</dd></div>
            <div><dt class="opacity-60">{{ __('inventory.serial.field.order') }}</dt><dd class="font-mono">{{ $serial->manufacturingOrder?->number ?? '—' }}</dd></div>
            <div><dt class="opacity-60">{{ __('inventory.serial.field.shipped_at') }}</dt><dd>{{ $serial->shipped_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
        </dl>
    </x-card>
</x-page-shell>
@endsection
