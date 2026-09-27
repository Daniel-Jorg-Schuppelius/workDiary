{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : return.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rücksendung anmelden (MVP-935). Erwartet: $deliveries, $serials, $assets --}}
@extends('customer.layout')

@section('title', __('claims.portal_return.title'))

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('claims.portal_return.title') }}</h1>
    <p class="text-sm text-muted">{{ __('claims.portal_return.intro') }}</p>

    @if ($deliveries->isEmpty() && $assets->isEmpty())
        <x-empty-state framed icon="assignment_return" :title="__('claims.portal_return.empty')" />
    @else
        <x-card>
            <form method="POST" action="{{ route('customer.returns.store') }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                @csrf
                @if ($deliveries->isNotEmpty())
                    <x-select-field name="delivery_id" :label="__('claims.portal_return.field.delivery')">
                        <option value="">—</option>
                        @foreach ($deliveries as $delivery)
                            <option value="{{ $delivery->sqid }}" @selected(old('delivery_id') === $delivery->sqid)>{{ $delivery->name_snapshot }} ({{ $delivery->sku_snapshot }}) — {{ $delivery->delivered_at?->format('d.m.Y') }}</option>
                        @endforeach
                    </x-select-field>
                    @php($allSerials = collect($serials)->flatten()->unique()->values())
                    @if ($allSerials->isNotEmpty())
                        <x-input-field name="serial_no" :label="__('claims.portal_return.field.serial_no')" :value="old('serial_no')" list="portal-return-serials" />
                        <datalist id="portal-return-serials">
                            @foreach ($allSerials as $serial)
                                <option value="{{ $serial }}"></option>
                            @endforeach
                        </datalist>
                    @endif
                @endif
                @if ($assets->isNotEmpty())
                    <x-select-field name="asset_id" :label="__('claims.portal_return.field.asset')">
                        <option value="">—</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->sqid }}" @selected(old('asset_id') === $asset->sqid)>{{ $asset->name }}@if ($asset->serial_no) ({{ $asset->serial_no }})@endif</option>
                        @endforeach
                    </x-select-field>
                @endif
                <x-input-field name="quantity" type="number" step="0.001" min="0.001" :label="__('claims.portal_return.field.quantity')" :value="old('quantity', '1')" />
                <x-input-field name="title" :label="__('claims.portal_return.field.title')" :value="old('title')" required />
                <x-textarea-field name="description" :label="__('claims.portal_return.field.description')" rows="4" required>{{ old('description') }}</x-textarea-field>
                <div>
                    <label class="label" for="portal-return-photos"><span class="label-text">{{ __('claims.portal_return.field.photos') }}</span></label>
                    <input id="portal-return-photos" type="file" name="photos[]" multiple accept="image/*,application/pdf" class="file-input file-input-bordered file-input-sm w-full">
                    @error('photos')<p class="text-sm text-error">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="text-sm text-error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end"><x-button type="submit">{{ __('claims.portal_return.submit') }}</x-button></div>
            </form>
        </x-card>
    @endif
</div>
@endsection
