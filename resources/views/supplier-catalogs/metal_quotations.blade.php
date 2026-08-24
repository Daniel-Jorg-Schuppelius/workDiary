{{--
  Created on   : Sun Aug 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : metal_quotations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.metal.title'))
@section('nav-title', __('procurement.catalog.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('procurement.metal.hint')" />

        <x-card>

            <form method="POST" action="{{ route('supplier-catalogs.metal-quotations.store') }}" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @csrf
                <x-select-field name="metal" :label="__('procurement.metal.col.metal')" required>
                    @foreach ($metals as $metal)
                        <option value="{{ $metal }}" @selected($metal === 'CU')>{{ $metal }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="price_per_kg" type="number" step="0.0001" min="0" :label="__('procurement.metal.col.price')" required />
                <x-input-field name="quoted_at" type="date" :label="__('procurement.metal.col.date')" required :value="now()->toDateString()" max="{{ now()->toDateString() }}" />
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('procurement.metal.action.save') }}</button>
                </div>
            </form>

            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('procurement.metal.col.metal') }}</th>
                        <th class="text-right">{{ __('procurement.metal.col.price') }}</th>
                        <th>{{ __('procurement.metal.col.date') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                        @forelse ($quotations as $quotation)
                            <tr class="hover">
                                <td class="font-mono">{{ $quotation->metal }}</td>
                                <td class="text-right">{{ $quotation->price_per_kg?->getAmount() }} €/kg</td>
                                <td>{{ $quotation->quoted_at->format('d.m.Y') }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('supplier-catalogs.metal-quotations.destroy', $quotation) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('procurement.metal.action.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="4" :title="__('procurement.metal.empty')" compact />
                        @endforelse
            </x-table>
        </x-card>
    </div>
</x-page-shell>
@endsection
