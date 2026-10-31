{{--
  Created on   : Sat Oct 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _holder_picker.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Neuer Halter: Kunde oder Fremdkunde eines Kunden (Abtretung, Übernahme).
  Erwartet $customers, $foreignByCustomer (ProvidesHolderPicker), $mode
  (customer|foreign) und $customerDefault (Sqid oder leer).
--}}
    <div class="space-y-3" x-data="resaleHolderPicker"
         data-map="{{ json_encode($foreignByCustomer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
         data-holder="{{ $mode }}"
         data-customer="{{ old('customer_id', $customerDefault) }}"
         data-foreign="{{ old('foreign_customer_id', '') }}">
        <x-select-field name="mode" :label="__('resale.transfer.mode')" x-model="holder">
            <option value="customer" @selected($mode === 'customer')>{{ __('resale.inbox.mode_customer') }}</option>
            <option value="foreign" @selected($mode === 'foreign')>{{ __('resale.inbox.mode_foreign') }}</option>
        </x-select-field>
        <x-select-field name="customer_id" :label="__('resale.inbox.customer')" x-model="customer" required>
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected(old('customer_id', $customerDefault) === $customer->sqid)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
        <div x-show="showForeign()">
            <x-select-field name="foreign_customer_id" :label="__('resale.inbox.foreign')" x-model="foreign">
                <option value="">—</option>
                <template x-for="fc in options()" :key="fc.sqid">
                    <option :value="fc.sqid" x-text="fc.name"></option>
                </template>
            </x-select-field>
        </div>
        <p class="text-xs text-warning" x-show="noForeign()">{{ __('resale.dialog.no_foreign_customers') }}</p>
    </div>
