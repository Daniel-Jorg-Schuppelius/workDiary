{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : journal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lagerjournal (MVP-1584): Bewegungen in Basiseinheit samt Eingabe in der erfassten Einheit. --}}
@extends('layouts.app')
@section('title', __('inventory.journal.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.journal.title'))
@include('partials.page-fill')

@php
    $qty = static fn ($value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $value, 4, withThousandsSeparator: true, trimTrailingZeros: true);
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.journal.subtitle')">

    @include('inventory._tabs')

    <x-filter-bar :action="route('inventory.journal')" :reset="route('inventory.journal')">
        <x-filter-field :label="__('Suche')" for="journal-q" class="flex-1 min-w-60">
            <input id="journal-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('inventory.journal.search_placeholder') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-filter-field :label="__('inventory.field.warehouse')" for="journal-warehouse">
            <select id="journal-warehouse" name="warehouse" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->sqid }}" @selected($selected?->id === $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table :zebra="true" scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('inventory.journal.col.occurred_at') }}</th>
                <th>{{ __('inventory.field.variant') }}</th>
                <th>{{ __('inventory.field.warehouse') }}</th>
                <th>{{ __('inventory.field.movement') }}</th>
                <th>{{ __('inventory.journal.col.state') }}</th>
                <th class="text-right">{{ __('inventory.journal.col.qty_base') }}</th>
                <th class="text-right">{{ __('inventory.journal.col.entered') }}</th>
                <th>{{ __('inventory.journal.col.lot') }}</th>
                <th>{{ __('inventory.journal.col.actor') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($movements as $movement)
            <tr>
                <td class="whitespace-nowrap">{{ $movement->occurred_at?->fdatetime() }}</td>
                <td>
                    {{ $movement->variant?->article?->name ?? '—' }}
                    <div class="text-xs text-muted font-mono">{{ $movement->variant?->sku ?? $movement->variant?->name }}</div>
                </td>
                <td>{{ $movement->warehouse?->name }}@if ($movement->bin) · {{ $movement->bin->code }}@endif</td>
                <td><x-status-badge>{{ $movement->movement_type->label() }}</x-status-badge></td>
                <td>{{ $movement->stock_state->label() }}</td>
                <td class="text-right tabular-nums whitespace-nowrap">{{ $qty($movement->qty_base) }} {{ $movement->variant?->article?->base_unit }}</td>
                <td class="text-right tabular-nums whitespace-nowrap">
                    @if ($movement->original_qty !== null && $movement->original_unit !== null)
                        {{ $qty($movement->original_qty->getNumericValue()) }} {{ $movement->original_unit }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="font-mono text-sm">{{ $movement->lot?->lot_no ?? '—' }}</td>
                <td>{{ $movement->actor?->name ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="9" icon="receipt_long" :title="__('inventory.journal.empty')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$movements" standing />
</x-index-page>
@endsection
