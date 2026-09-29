{{--
  Created on   : Wed Aug 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Materialkosten zuordnen (Feature: Gewinndarstellung). Erwartet:
     $customer, $purchaseDocuments (Collection<PurchaseDocument>, alle Belegquellen — MVP-1036), $projects. --}}

@php
    $money = fn ($v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(
        $v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true
    );
@endphp

<x-modal
    :title="__('customer-material.add_title')"
    :eyebrow="$customer->name"
    icon="shopping_cart"
    tone="primary"
    :action="route('customers.material-costs.store', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-form-group :legend="__('customer-material.source')" icon="receipt_long" tone="primary" cols="1"
                  :description="__('customer-material.source_hint')">
        <x-select-field name="document" :label="__('customer-material.voucher')"
                        :hint="__('customer-material.voucher_hint')">
            <option value="">{{ __('customer-material.manual_amount') }}</option>
            @foreach ($purchaseDocuments as $document)
                <option value="{{ $document->key }}" @selected(old('document') === $document->key)>
                    {{ $document->reference() }}
                    @if ($document->vendorName) · {{ $document->vendorName }} @endif
                    · {{ $money($document->net) }} {{ $document->currency->value }}
                    @if ($document->date) · {{ $document->date->fdate() }} @endif
                </option>
            @endforeach
        </x-select-field>
        <x-input-field name="description" type="text" maxlength="500"
                       :label="__('customer-material.description')"
                       :hint="__('customer-material.description_hint')"
                       :value="old('description')" />
    </x-form-group>

    <x-form-group :legend="__('customer-material.allocation')" icon="payments" tone="warning" cols="2">
        <x-input-field name="allocated_amount" type="number" step="0.01" min="0.01" required
                       :label="__('customer-material.amount')"
                       :hint="__('customer-material.amount_hint')"
                       :value="old('allocated_amount')" />
        <x-input-field name="allocated_on" type="date" required
                       :label="__('customer-material.date')"
                       :value="old('allocated_on', now()->toDateString())" />
        <x-project-select :label="__('customer-material.project')" :hint="__('customer-material.project_hint')"
            :placeholder="__('customer-material.no_project')" :group="false"
            :projects="$projects" :selected="(string) old('project_id')" />
    </x-form-group>
</x-modal>
