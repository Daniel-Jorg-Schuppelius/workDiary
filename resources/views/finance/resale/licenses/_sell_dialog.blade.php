{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _sell_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einzelne Lizenz verkaufen (MVP-1024): genau eine verfügbare Lizenz mit
  ihrem Schlüsselsatz an einen Kunden. Der Token macht ein doppeltes Absenden
  harmlos.
--}}
<x-modal
    :title="__('resale.license.action.sell')"
    :eyebrow="$product?->name ?? __('resale.license.title')"
    icon="add_shopping_cart" tone="primary" size="lg"
    :action="route('finance.resale.licenses.sell.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.license.action.sell_submit')">
    <input type="hidden" name="token" value="{{ $token }}">
    @if ($available->isEmpty())
        <div class="alert alert-warning text-sm" role="status">
            <x-icon name="inventory" />
            <span>{{ __('resale.license.hint.none_available') }}</span>
        </div>
    @else
        <x-form-group :legend="__('resale.license.field.license')" icon="key" tone="primary" cols="2">
            <x-select-field name="unit_id" :label="__('resale.license.field.license')" required span="2" :hint="__('resale.license.hint.oldest_first')">
                @foreach ($available->groupBy(static fn ($unit) => $unit->batch->product->name) as $productName => $group)
                    <optgroup label="{{ $productName }}">
                        @foreach ($group as $unit)
                            <option value="{{ $unit->sqid }}" @selected(old('unit_id', $suggested?->sqid) === $unit->sqid)>{{ $unit->label() }} · {{ $unit->batch->purchased_on->fdate() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-select-field>
            <x-input-field name="sold_on" type="date" :label="__('resale.license.field.sold_on')" :value="old('sold_on', now()->toDateString())" required />
            <x-input-field name="invoice_reference" :label="__('resale.license.field.invoice_reference')" :value="old('invoice_reference')" maxlength="80" :hint="__('resale.license.hint.invoice_reference')" />
        </x-form-group>
        <x-form-group :legend="__('resale.license.field.holder')" icon="person" cols="1">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2"
                 x-data="resaleHolderPicker"
                 data-map="{{ json_encode($foreignByCustomer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
                 data-holder="customer"
                 data-customer="{{ old('customer_id') }}"
                 data-foreign="{{ old('foreign_customer_id') }}">
                <x-select-field name="customer_id" :label="__('resale.license.field.customer')" x-model="customer" required>
                    <option value="">—</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->sqid }}" @selected(old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
                    @endforeach
                </x-select-field>
                <div x-show="showForeign()">
                    <x-select-field name="foreign_customer_id" :label="__('resale.holder.foreign')" :hint="__('resale.license.hint.foreign')" x-model="foreign">
                        <option value="">{{ __('resale.holder.customer_self') }}</option>
                        <template x-for="fc in options()" :key="fc.sqid">
                            <option :value="fc.sqid" x-text="fc.name" :selected="fc.sqid === foreign"></option>
                        </template>
                    </x-select-field>
                </div>
            </div>
        </x-form-group>
        <p class="text-xs text-muted">{{ __('resale.license.hint.sell_no_invoice') }}</p>
    @endif
</x-modal>
