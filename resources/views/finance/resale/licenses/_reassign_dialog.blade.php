{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reassign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verkauf berichtigen (MVP-1024): dieselbe Lizenz nahtlos an einen anderen
  Kunden; alter und neuer Kunde bleiben in der Historie.
--}}
<x-modal
    :title="__('resale.license.action.reassign')"
    :eyebrow="$assignment->unit->label()"
    icon="swap_horiz" tone="warning" size="lg"
    :action="route('finance.resale.licenses.assignments.reassign.store', $assignment)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.license.action.reassign_submit')">
    <p class="text-sm text-muted">{{ __('resale.license.reassign_current', ['customer' => $assignment->holderLabel(), 'date' => $assignment->sold_on->fdate()]) }}</p>
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
            <x-select-field name="foreign_customer_id" :label="__('resale.holder.foreign')" x-model="foreign">
                <option value="">{{ __('resale.holder.customer_self') }}</option>
                <template x-for="fc in options()" :key="fc.sqid">
                    <option :value="fc.sqid" x-text="fc.name" :selected="fc.sqid === foreign"></option>
                </template>
            </x-select-field>
        </div>
    </div>
    <x-input-field name="reason" :label="__('resale.license.field.reason')" :value="old('reason')" required maxlength="255" />
</x-modal>
