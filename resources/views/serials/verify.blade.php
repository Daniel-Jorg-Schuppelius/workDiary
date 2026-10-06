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
            <input aria-label="{{ __('inventory.serial.verify.placeholder') }}" name="serial" value="{{ $query }}" autofocus placeholder="{{ __('inventory.serial.verify.placeholder') }}"
                   class="input input-bordered w-full font-mono">
            <x-button type="submit" tone="primary" size="md">{{ __('inventory.serial.action.search') }}</x-button>
        </form>
    </x-card>

    @if ($searched)
        @if ($serial === null)
            <x-card>
                <div role="alert" class="alert alert-error">
                    <x-icon name="gpp_bad" />
                    {{ __('inventory.serial.verify.not_found') }}
                </div>
            </x-card>
        @else
            <x-card>
                <div class="flex items-center gap-2">
                    <x-icon name="verified" class="text-success" />
                    <span class="font-mono">{{ $serial->serial_no }}</span>
                    <x-status-badge>{{ $serial->status->label() }}</x-status-badge>
                </div>
                <x-detail-grid layout="cells" class="mt-4">
                    <x-detail-grid.row :label="__('inventory.serial.field.article')">{{ $serial->article?->name }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('inventory.serial.field.source')">{{ $serial->source->label() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('inventory.serial.field.customer')">{{ $serial->customer?->name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('inventory.serial.field.shipped_at')">{{ $serial->shipped_at?->fdate() ?? '—' }}</x-detail-grid.row>
                </x-detail-grid>
                <a href="{{ route('serials.show', $serial) }}" class="link link-primary text-sm mt-3 inline-block">{{ __('Details') }} →</a>
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
