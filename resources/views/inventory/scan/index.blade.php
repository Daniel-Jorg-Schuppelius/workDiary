@extends('layouts.app')
@section('title', __('inventory.scan.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.scan.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('inventory.scan.subtitle')" />
    </x-slot:toolbar>

    {{-- Auflösen (GET) --}}
    <x-card>
        <h2 class="font-semibold mb-2">{{ __('inventory.scan.title') }}</h2>
        <form method="GET" action="{{ route('inventory.scan') }}" class="flex items-end gap-2">
            <div class="fieldset grow"><label class="fieldset-label">{{ __('inventory.scan.code') }}</label>
                <input name="code" value="{{ $code }}" autofocus inputmode="text" autocomplete="off"
                       class="input input-bordered w-full font-mono" placeholder="GTIN / SKU / SN / LOT"></div>
            <button type="submit" class="btn">{{ __('inventory.serial.action.search') }}</button>
        </form>

        @if ($match)
            @if ($match->found())
                <div class="alert mt-3">
                    <span class="badge">{{ $match->type->value }}</span>
                    <span>{{ $match->variant?->article?->name }} — {{ $match->variant?->name ?? $match->variant?->sku }}</span>
                </div>
            @else
                <div class="alert alert-warning mt-3">{{ __('inventory.serial.verify.not_found') }}</div>
            @endif
        @endif
    </x-card>

    {{-- Buchen (POST) --}}
    @if ($canPost)
        <x-card>
            <h2 class="font-semibold mb-2">{{ __('inventory.scan.book') }}</h2>
            <form method="POST" action="{{ route('inventory.scan.book') }}" class="flex flex-col gap-2">
                @csrf
                <input type="hidden" name="code" value="{{ $code }}">
                <div class="fieldset"><label class="fieldset-label">{{ __('inventory.scan.code') }}</label>
                    <input name="code" value="{{ $code }}" required class="input input-bordered font-mono"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="fieldset"><label class="fieldset-label">{{ __('inventory.scan.action_label') }}</label>
                        <select name="action" class="select select-bordered">
                            @foreach ($actions as $a)
                                <option value="{{ $a->value }}">{{ $a->label() }}</option>
                            @endforeach
                        </select></div>
                    <div class="fieldset"><label class="fieldset-label">{{ __('inventory.scan.qty') }}</label>
                        <input name="qty" type="number" step="0.0001" min="0.0001" value="1" class="input input-bordered"></div>
                </div>
                <div class="fieldset"><label class="fieldset-label">{{ __('inventory.field.warehouse') }}</label>
                    <select name="warehouse" class="select select-bordered">
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->sqid }}">{{ $wh->name }}</option>
                        @endforeach
                    </select></div>
                <div class="fieldset"><label class="fieldset-label">{{ __('inventory.scan.target') }}</label>
                    <select name="target" class="select select-bordered">
                        <option value="">—</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->sqid }}">{{ $wh->name }}</option>
                        @endforeach
                    </select></div>
                <button type="submit" class="btn btn-primary">{{ __('inventory.scan.book') }}</button>
            </form>
        </x-card>
    @endif
</x-page-shell>
@endsection
