{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rma_stock_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lagerbezug eines Rückläufers (MVP-1576). Erwartet: $claim, $rma (ClaimRmaReturn|null),
     $prefix (id-Präfix), $stock (warehouses, variants, serials, lots), $warehouseRequired.
     Vorbelegt aus dem Rückläufer, sonst aus Artikel und Seriennummer der Akte. --}}
@php
    $serialId = $rma?->stock_serial_id ?? $claim->stock_serial_id;
    $variantId = $rma?->article_variant_id
        ?? $stock['serials']->firstWhere('id', $serialId)?->article_variant_id
        ?? ($claim->article_id !== null && $stock['variants']->count() === 1 ? $stock['variants']->first()->id : null);
    $lotId = $rma?->stock_lot_id ?? $claim->stock_lot_id;
    $warehouseId = $rma?->warehouse_id ?? ($stock['warehouses']->count() === 1 ? $stock['warehouses']->first()->id : null);
    $qty = $rma?->qty ?? ($serialId !== null ? '1' : null);
@endphp
<x-form-group :legend="__('claims.rma.group_stock')" :description="__('claims.rma.hint_stock')" icon="warehouse" tone="primary" cols="2">
    <x-select-field name="article_variant_id" :id="$prefix . '-variant'" :label="__('validation.attributes.article_variant_id')" span="2"
                    data-prefill-from="stock_serial_id" data-prefill-key="variant">
        <option value="">{{ __('claims.rma.option.no_stock') }}</option>
        @foreach ($stock['variants']->groupBy(fn ($v) => $v->article?->name ?? '') as $articleName => $group)
            <optgroup label="{{ $articleName }}">
                @foreach ($group as $variant)
                    <option value="{{ $variant->sqid }}" @selected($variantId === $variant->id)>{{ trim(($variant->sku ? $variant->sku . ' · ' : '') . ($variant->name ?? $articleName)) }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </x-select-field>
    <x-select-field name="warehouse_id" :id="$prefix . '-warehouse'" :label="__('validation.attributes.warehouse_id')"
                    :hint="$warehouseRequired ? __('claims.rma.hint_warehouse') : null">
        <option value="">—</option>
        @foreach ($stock['warehouses'] as $warehouse)
            <option value="{{ $warehouse->sqid }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="qty" :id="$prefix . '-qty'" type="number" min="0.0001" step="0.0001" :label="__('validation.attributes.qty')"
                   :value="$qty !== null ? (string) (float) $qty : ''" />
    @if ($stock['serials']->isNotEmpty())
        <x-select-field name="stock_serial_id" :id="$prefix . '-serial'" :label="__('validation.attributes.stock_serial_id')"
                        :hint="__('claims.rma.hint_serial')">
            <option value="">—</option>
            @foreach ($stock['serials'] as $serial)
                <option value="{{ $serial->sqid }}" data-prefill-variant="{{ \App\Support\Sqid::encode(\App\Models\Article\ArticleVariant::class, $serial->article_variant_id) }}"
                        @selected($serialId === $serial->id)>{{ $serial->serial_no }}</option>
            @endforeach
        </x-select-field>
    @endif
    @if ($stock['lots']->isNotEmpty())
        <x-select-field name="stock_lot_id" :id="$prefix . '-lot'" :label="__('validation.attributes.stock_lot_id')">
            <option value="">—</option>
            @foreach ($stock['lots'] as $lot)
                <option value="{{ $lot->sqid }}" @selected($lotId === $lot->id)>{{ $lot->lot_no }}</option>
            @endforeach
        </x-select-field>
    @endif
</x-form-group>
