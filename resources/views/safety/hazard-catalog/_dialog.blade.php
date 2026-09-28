{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gefährdung im Katalog anlegen/bearbeiten (MVP-1002). Variablen: $item (HazardCatalogItem|null)
--}}
<x-modal :title="$item !== null ? __('safety.catalog.action.edit') : __('safety.catalog.action.add')" :eyebrow="__('safety.catalog.title')"
         icon="library_books" tone="primary"
         :action="$item !== null ? route('safety.hazard-catalog.update', $item) : route('safety.hazard-catalog.store')"
         :method="$item !== null ? 'PUT' : 'POST'" :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    <x-form-group :legend="__('safety.catalog.title')" icon="library_books" tone="primary" cols="2">
        <x-input-field name="category" :label="__('safety.catalog.field.category')" required minlength="2" maxlength="120" span="2"
                       :value="old('category', $item?->category)" />
        <x-input-field name="hazard" :label="__('safety.catalog.field.hazard')" required minlength="2" maxlength="255" span="2"
                       :value="old('hazard', $item?->hazard)" />
        <x-textarea-field name="measure" :label="__('safety.catalog.field.measure')" rows="3" maxlength="10000" span="2"
                          :value="old('measure', $item?->measure)" />
        <x-input-field name="severity" type="number" min="1" max="5" required :label="__('safety.catalog.field.severity')"
                       :value="old('severity', $item?->severity ?? 3)" />
        <x-input-field name="likelihood" type="number" min="1" max="5" required :label="__('safety.catalog.field.likelihood')"
                       :value="old('likelihood', $item?->likelihood ?? 3)" />
        <x-checkbox-field name="is_active" span="2" :label="__('safety.catalog.field.is_active')"
                          :checked="old('is_active', $item?->is_active ?? true)" />
    </x-form-group>
</x-modal>
