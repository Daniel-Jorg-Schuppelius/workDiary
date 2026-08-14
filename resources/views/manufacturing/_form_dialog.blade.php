{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $isDialog, $articles, $variants, $warehouses --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('manufacturing.order.action.create')"
    :eyebrow="__('manufacturing.order.title')"
    icon="precision_manufacturing"
    tone="primary"
    :action="route('manufacturing-orders.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('manufacturing-orders.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="precision_manufacturing" tone="primary" cols="2">
        <x-select-field name="article" :label="__('Artikel')" required>
            @foreach ($articles as $article)
                <option value="{{ $article->sqid }}">{{ $article->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="variant" :label="__('manufacturing.quantity_kind.per_unit') . ' — ' . __('Variante')">
            <option value="">—</option>
            @foreach ($variants as $variant)
                <option value="{{ $variant->sqid }}">{{ $variant->article?->name }} — {{ $variant->name ?? $variant->option_signature }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="target_qty" type="number" :label="__('manufacturing.order.field.target_qty')" required step="0.0001" min="0.0001" :value="old('target_qty', 1)" />
        <x-input-field name="unit" :label="__('article.field.base_unit')" required maxlength="20" :value="old('unit', 'Stk')" />

        <x-select-field name="warehouse" :label="__('inventory.field.warehouse')">
            <option value="">—</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->sqid }}">{{ $warehouse->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="priority" type="number" :label="__('inventory.overview.priority')" min="1" :value="old('priority', 100)" />
    </x-form-group>
</x-modal>
