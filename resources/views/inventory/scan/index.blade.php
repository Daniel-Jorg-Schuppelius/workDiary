{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.scan.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.scan.title'))

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('inventory.scan.subtitle')" />
    </x-slot:toolbar>

    {{-- ── Scannen / Auflösen ──────────────────────────────────────── --}}
    <x-card>
        <div class="mb-3 flex items-center gap-2">
            <span class="grid size-9 shrink-0 place-items-center rounded-box bg-primary/10 text-primary">
                <x-icon name="qr_code_scanner" class="text-xl" />
            </span>
            <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('inventory.scan.title') }}</h2>
        </div>

        <form method="GET" action="{{ route('inventory.scan') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="fieldset min-w-0 grow">
                <label class="fieldset-label" for="scan-code">{{ __('inventory.scan.code') }}</label>
                <div class="relative">
                    <x-icon name="barcode_scanner" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-base text-base-content/40" />
                    <input id="scan-code" name="code" value="{{ $code }}" autofocus inputmode="text" autocomplete="off"
                           class="input input-sm input-bordered w-full pl-9 font-mono tracking-wide"
                           placeholder="GTIN / SKU / SN / LOT">
                </div>
            </div>
            <div class="fieldset">
                {{-- Unsichtbarer Label-Platzhalter, damit der Button bündig zum
                     Eingabefeld steht (Label + Control wie die Feld-Spalte). --}}
                <label class="fieldset-label invisible select-none hidden sm:block" aria-hidden="true">&nbsp;</label>
                <x-icon-btn icon="search" tone="primary" size="sm" type="submit" show-label
                            class="w-full sm:w-auto">{{ __('inventory.serial.action.search') }}</x-icon-btn>
            </div>
        </form>

        @if ($match)
            @if ($match->found())
                <div class="mt-4 flex items-center gap-3 rounded-box border border-success/40 bg-success/10 p-3">
                    <x-icon name="check_circle" class="shrink-0 text-2xl text-success" />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge tone="success" size="sm">{{ $match->type->value }}</x-status-badge>
                            <span class="truncate font-semibold">{{ $match->variant?->article?->name }}</span>
                        </div>
                        <p class="mt-0.5 truncate font-mono text-sm text-base-content/70">{{ $match->variant?->name ?? $match->variant?->sku }}</p>
                    </div>
                </div>
            @else
                <div class="alert alert-warning mt-4">
                    <x-icon name="error" />
                    <span>{{ __('inventory.serial.verify.not_found') }}</span>
                </div>
            @endif
        @endif
    </x-card>

    {{-- ── Buchen ──────────────────────────────────────────────────── --}}
    @if ($canPost)
        <x-card>
            <div class="mb-3 flex items-center gap-2">
                <span class="grid size-9 shrink-0 place-items-center rounded-box bg-primary/10 text-primary">
                    <x-icon name="inventory" class="text-xl" />
                </span>
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('inventory.scan.book') }}</h2>
            </div>

            <form method="POST" action="{{ route('inventory.scan.book') }}" class="flex flex-col gap-3">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-label" for="book-code">{{ __('inventory.scan.code') }}</label>
                    <input id="book-code" name="code" value="{{ $code }}" required
                           class="input input-sm input-bordered w-full font-mono">
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('inventory.scan.action_label') }}</label>
                        <select name="action" class="select select-sm select-bordered w-full">
                            @foreach ($actions as $a)
                                <option value="{{ $a->value }}">{{ $a->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('inventory.scan.qty') }}</label>
                        <input name="qty" type="number" step="0.0001" min="0.0001" value="1"
                               class="input input-sm input-bordered w-full">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('inventory.field.warehouse') }}</label>
                        <select name="warehouse" class="select select-sm select-bordered w-full">
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->sqid }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('inventory.scan.target') }}</label>
                        <select name="target" class="select select-sm select-bordered w-full">
                            <option value="">—</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->sqid }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <x-icon-btn icon="inventory_2" tone="primary" size="sm" type="submit" show-label
                            class="mt-1 self-start">{{ __('inventory.scan.book') }}</x-icon-btn>
            </form>
        </x-card>
    @endif
</x-page-shell>
@endsection
