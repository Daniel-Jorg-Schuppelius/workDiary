{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rückruf anlegen/bearbeiten (MVP-921), nur im Entwurf. Erwartet: $recall (?Recall), $variants --}}
@php
    $isEdit = $recall !== null;
    $orderNumbers = $isEdit && $recall->manufacturing_order_ids
        ? \App\Models\Manufacturing\ManufacturingOrder::query()->whereIn('id', $recall->manufacturing_order_ids)->pluck('number')->implode(', ')
        : '';
@endphp
<x-modal
    :title="$isEdit ? __('recall.dialog.edit') : __('recall.dialog.create')"
    :eyebrow="__('recall.title')"
    icon="campaign"
    tone="warning"
    :action="$isEdit ? route('recalls.update', $recall) : route('recalls.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('recall.action.save') : __('recall.action.create')"
>
    <x-form-group :legend="__('recall.section.recall')" icon="campaign" tone="warning" cols="2">
        @unless ($isEdit)
            <x-select-field name="article_variant_id" :label="__('recall.field.variant')" required span="2">
                <option value="">—</option>
                @foreach ($variants->groupBy(fn ($v) => $v->article?->name ?? '') as $articleName => $group)
                    <optgroup label="{{ $articleName }}">
                        @foreach ($group as $variant)
                            <option value="{{ $variant->sqid }}" @selected(old('article_variant_id') === $variant->sqid)>{{ trim(($variant->sku ? $variant->sku . ' · ' : '') . ($variant->name ?? $articleName)) }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-select-field>
        @endunless
        <x-input-field name="title" :label="__('recall.field.title')" :value="old('title', $recall?->title)" required />
        <x-select-field name="kind" :label="__('recall.field.kind')" required>
            @foreach (\App\Enums\Inventory\RecallKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(old('kind', $recall?->kind->value) === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="reason" :label="__('recall.field.reason')" rows="3" span="2" required>{{ old('reason', $recall?->reason) }}</x-textarea-field>
        <x-textarea-field name="customer_message" :label="__('recall.field.customer_message')" :hint="__('recall.hint.customer_message')" rows="3" span="2">{{ old('customer_message', $recall?->customer_message) }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('recall.section.scope')" :description="__('recall.hint.scope')" icon="filter_alt" tone="warning" cols="2">
        <x-input-field name="manufacturing_orders" :label="__('recall.field.manufacturing_orders')" :hint="__('recall.hint.list')" :value="old('manufacturing_orders', $orderNumbers)" span="2" />
        <x-date-range class="md:col-span-2" layout="split" form-control from-name="delivered_from" to-name="delivered_until"
                      :from="old('delivered_from', $recall?->delivered_from?->format('Y-m-d'))" :to="old('delivered_until', $recall?->delivered_until?->format('Y-m-d'))"
                      :from-label="__('recall.field.delivered_from')" :to-label="__('recall.field.delivered_until')" />
        <x-textarea-field name="serial_numbers" :label="__('recall.field.serial_numbers')" :hint="__('recall.hint.list')" rows="3" span="2">{{ old('serial_numbers', implode("\n", (array) $recall?->serial_numbers)) }}</x-textarea-field>
        <x-checkbox-field name="is_blocking_stock" :label="__('recall.field.is_blocking_stock')" :checked="(bool) old('is_blocking_stock', $recall?->is_blocking_stock ?? true)" span="2" />
    </x-form-group>
</x-modal>
