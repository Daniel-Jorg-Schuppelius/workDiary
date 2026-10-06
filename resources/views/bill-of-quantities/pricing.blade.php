{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pricing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- LV bepreisen (MVP-1056). Erwartet: $bill, $items, $kinds (list<CostKind>), $editable --}}
@extends('layouts.app')
@section('title', __('gaeb.pricing.title') . ' — ' . $bill->name)
@section('nav-title', $bill->name)

@php
    $amount = static fn (?\CommonToolkit\ValueObjects\Money $m): string => $m === null ? '' : $m->withScale(2)->getAmount();
@endphp

@section('content')
<x-index-page :subtitle="$bill->name" back-route="bill-of-quantities.show" :back-params="[$bill]" :back-label="__('gaeb.pricing.back')">

    @unless ($editable)
        <div role="status" class="alert alert-info text-sm">{{ __('gaeb.pricing.locked') }}</div>
    @endunless

    <form method="POST" action="{{ route('bill-of-quantities.pricing.update', $bill) }}" data-entry-form>
        @csrf
        @method('PUT')
        <x-card :title="__('gaeb.pricing.title')" padding="p-0">
            <p class="px-4 pt-3 text-xs text-muted">{{ __('gaeb.pricing.hint') }}</p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('gaeb.efb.223.col.reference') }}</th>
                        <th>{{ __('gaeb.efb.223.col.short_text') }}</th>
                        <th class="text-right">{{ __('gaeb.efb.223.col.quantity') }}</th>
                        @foreach ($kinds as $kind)<th class="text-right">{{ $kind->label() }}</th>@endforeach
                        <th class="text-right">{{ __('gaeb.efb.223.col.unit_price') }}</th>
                        <th class="text-right">{{ __('gaeb.pricing.total') }}</th>
                        <th>{{ __('gaeb.pricing.not_offered') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($items as $item)
                    @php
                        $key = $item->sqid;
                        $components = array_values((array) ($item->unit_price_components ?? []));
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $item->reference_no }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) $item->short_text, 80) }}@if (! $item->type->isBillable()) <x-status-badge size="xs">{{ $item->type->label() }}</x-status-badge>@endif</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ $item->quantity?->getNumericValue() !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity->getNumericValue(), 3, trimTrailingZeros: true) : '—' }} {{ $item->unit }}</td>
                        @foreach ($kinds as $i => $kind)
                            <td class="text-right">
                                <input type="number" step="0.01" min="0" name="rows[{{ $key }}][components][{{ $i }}]" id="price-{{ $key }}-{{ $i }}"
                                       aria-label="{{ $item->reference_no }} — {{ $kind->label() }}"
                                       value="{{ old('rows.' . $key . '.components.' . $i, $components[$i] ?? '') }}"
                                       class="input input-sm input-bordered w-24 text-right" @disabled(! $editable)>
                            </td>
                        @endforeach
                        <td class="text-right">
                            <input type="number" step="0.01" min="0" name="rows[{{ $key }}][unit_price]" id="price-{{ $key }}-ep"
                                   aria-label="{{ $item->reference_no }} — {{ __('gaeb.efb.223.col.unit_price') }}"
                                   value="{{ old('rows.' . $key . '.unit_price', $amount($item->unit_price)) }}"
                                   class="input input-sm input-bordered w-28 text-right" @disabled(! $editable)>
                        </td>
                        <td class="text-right tabular-nums">{{ $item->total_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->total_price->toFloat(), 2, withThousandsSeparator: true) : '—' }}</td>
                        <td>
                            <input type="hidden" name="rows[{{ $key }}][not_offered]" value="0">
                            <input type="checkbox" value="1" name="rows[{{ $key }}][not_offered]" id="price-{{ $key }}-no" class="checkbox checkbox-sm"
                                   aria-label="{{ $item->reference_no }} — {{ __('gaeb.pricing.not_offered') }}" @checked(old('rows.' . $key . '.not_offered', $item->not_offered)) @disabled(! $editable)>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon="price_change" :colspan="6 + count($kinds)" :title="__('gaeb.pricing.empty')" compact />
                @endforelse
            </x-table>
        </x-card>
        <x-validation-errors />
        @if ($editable && $items->isNotEmpty())
            <div class="mt-3 flex justify-end"><x-button type="submit">{{ __('Speichern') }}</x-button></div>
        @endif
    </form>
</x-index-page>
@endsection
