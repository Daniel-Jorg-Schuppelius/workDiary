{{--
  Created on   : Wed Aug 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _stock_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Lagerentnahme zugunsten eines Kunden (bucht Materialkosten zum gleitenden
     Durchschnitt). Erwartet: $customer, $warehouses, $variants, $projects. --}}

<x-modal
    :title="__('customer-material.stock_title')"
    :eyebrow="$customer->name"
    icon="inventory_2"
    tone="primary"
    :action="route('customers.material-costs.stock.store', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer-material.stock_issue')">

    <x-form-group :legend="__('customer-material.stock_source')" icon="inventory_2" tone="primary" cols="2"
                  :description="__('customer-material.stock_hint')">
        <x-select-field name="variant_id" :label="__('customer-material.article')" required>
            <option value="">{{ __('customer-material.choose') }}</option>
            @foreach ($variants as $variant)
                <option value="{{ $variant->sqid }}" @selected(old('variant_id') === $variant->sqid)>
                    {{ $variant->article?->name ?? $variant->sku }}
                    @if ($variant->option_signature) · {{ $variant->option_signature }} @endif
                </option>
            @endforeach
        </x-select-field>
        <x-select-field name="warehouse_id" :label="__('customer-material.warehouse')" required>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->sqid }}" @selected(old('warehouse_id', $warehouses->firstWhere('is_default', true)?->sqid) === $warehouse->sqid)>{{ $warehouse->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="qty" type="number" step="0.0001" min="0.0001" required
                       :label="__('customer-material.qty')"
                       :hint="__('customer-material.qty_hint')"
                       :value="old('qty')" />
        <x-input-field name="allocated_on" type="date"
                       :label="__('customer-material.date')"
                       :value="old('allocated_on', now()->toDateString())" />
        <x-select-field name="project_id" :label="__('customer-material.project')"
                        :hint="__('customer-material.project_hint')">
            <option value="">{{ __('customer-material.no_project') }}</option>
            @foreach ($projects as $project)
                <option value="{{ $project->sqid }}" @selected(old('project_id') === $project->sqid)>{{ $project->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
