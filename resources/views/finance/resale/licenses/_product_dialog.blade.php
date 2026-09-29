{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _product_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzprodukt anlegen/bearbeiten (MVP-1024): Schlüsselrollen und
  Meldebestand. Geänderte Rollen gelten erst für neue Pakete.
--}}
@php
    $editing = $product !== null;
    $labels = old('key_labels', $editing ? array_column($product->keyRoles(), 'label') : [__('resale.license.default_key', ['n' => 1]), __('resale.license.default_key', ['n' => 2])]);
@endphp
<x-modal
    :title="$editing ? __('resale.license.action.edit_product') : __('resale.license.action.new_product')"
    :eyebrow="__('resale.license.title')"
    icon="key" tone="primary" size="lg"
    :action="$editing ? route('finance.resale.licenses.products.update', $product) : route('finance.resale.licenses.products.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.license.action.save')">
    <x-form-group :legend="__('resale.license.field.product')" icon="inventory_2" tone="primary" cols="2">
        <x-input-field name="name" :label="__('resale.license.field.name')" :value="old('name', $product?->name)" required maxlength="160" />
        <x-input-field name="manufacturer" :label="__('resale.license.field.manufacturer')" :value="old('manufacturer', $product?->manufacturer)" maxlength="120" />
        <x-input-field name="reorder_level" type="number" min="0" :label="__('resale.license.field.reorder_level')" :value="old('reorder_level', $product?->reorder_level)" :hint="__('resale.license.hint.reorder_level')" />
        <x-article-catalog-select :articles="$catalogArticles" :selected="$articleFormKey" :label="__('resale.license.field.article')" :hint="__('resale.license.hint.article')" />
    </x-form-group>
    <x-form-group :legend="__('resale.license.field.key_roles')" icon="key" cols="2" :description="__('resale.license.hint.key_roles')">
        @for ($i = 0; $i < \App\Services\Reselling\License\LicenseStockService::MAX_KEY_ROLES; $i++)
            <x-input-field :name="'key_labels[' . $i . ']'" :id="'key-label-' . $i" :error="'key_labels.' . $i" :label="__('resale.license.field.key_label', ['n' => $i + 1])" :value="$labels[$i] ?? ''" maxlength="60" />
        @endfor
    </x-form-group>
    <x-textarea-field name="note" rows="2" :label="__('resale.license.field.note')" :value="old('note', $product?->note)" maxlength="2000" />
</x-modal>
