{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : verify.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.serial.verify.title') . ' — ' . __('inventory.serial.title'))
@section('nav-title', __('inventory.serial.verify.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('inventory.serial.verify.subtitle')" />
    </x-slot:toolbar>

    <x-card>
        <form method="GET" action="{{ route('serials.verify') }}" class="flex items-end gap-2">
            <input name="serial" value="{{ $query }}" autofocus placeholder="{{ __('inventory.serial.verify.placeholder') }}"
                   class="input input-bordered w-full font-mono">
            <x-button type="submit" tone="primary" size="md">{{ __('inventory.serial.action.search') }}</x-button>
        </form>
    </x-card>

    @if ($searched)
        @if ($serial === null)
            <x-card>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined" aria-hidden="true">gpp_bad</span>
                    {{ __('inventory.serial.verify.not_found') }}
                </div>
            </x-card>
        @else
            <x-card>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-success" aria-hidden="true">verified</span>
                    <span class="font-mono">{{ $serial->serial_no }}</span>
                    <span class="badge badge-sm badge-ghost">{{ $serial->status->label() }}</span>
                </div>
                <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm mt-4">
                    <div><dt class="opacity-60">{{ __('inventory.serial.field.article') }}</dt><dd>{{ $serial->article?->name }}</dd></div>
                    <div><dt class="opacity-60">{{ __('inventory.serial.field.source') }}</dt><dd>{{ $serial->source->label() }}</dd></div>
                    <div><dt class="opacity-60">{{ __('inventory.serial.field.customer') }}</dt><dd>{{ $serial->customer?->name ?? '—' }}</dd></div>
                    <div><dt class="opacity-60">{{ __('inventory.serial.field.shipped_at') }}</dt><dd>{{ $serial->shipped_at?->format('d.m.Y') ?? '—' }}</dd></div>
                </dl>
                <a href="{{ route('serials.show', $serial) }}" class="link link-primary text-sm mt-3 inline-block">{{ __('Details') }} →</a>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
