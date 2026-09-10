{{--
  Created on   : Tue Sep 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transfer_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzabtretung (Feature 152): einen Teil der Lizenzen eines Vertrags an
  einen anderen Halter abtreten — zwei Firmen im selben Haus, ein Vertrag
  beim Anbieter. Es entsteht ein Kind-Abo mit eigenem Halter, eigener Menge
  und eigenen Perioden; der Vertrag plant mit dem Rest. Verfügbarkeit und
  Vorbelegung ($available, $quantityDefault, $prefill) rechnet der Controller.
--}}
@php
    $mode = (string) old('mode', 'customer');
@endphp
<x-modal
    :title="__('resale.transfer.title', ['subscription' => $subscription->label])"
    icon="call_split"
    tone="primary"
    size="md"
    :action="route('finance.resale.transfer.store', $subscription->sqid)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.transfer.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.transfer.hint', ['holder' => $subscription->holderLabel(), 'quantity' => $subscription->quantity, 'available' => $available]) }}</div>
    <p class="text-xs text-muted">{{ __('resale.transfer.timeline_hint') }}</p>

    <div class="space-y-3" x-data="resaleHolderPicker"
         data-map="{{ json_encode($foreignByCustomer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
         data-holder="{{ $mode }}"
         data-customer="{{ old('customer_id', $prefill['customer_id']) }}"
         data-foreign="{{ old('foreign_customer_id', '') }}">
        <x-select-field name="mode" :label="__('resale.transfer.mode')" x-model="holder">
            <option value="customer" @selected($mode === 'customer')>{{ __('resale.inbox.mode_customer') }}</option>
            <option value="foreign" @selected($mode === 'foreign')>{{ __('resale.inbox.mode_foreign') }}</option>
        </x-select-field>
        <x-select-field name="customer_id" :label="__('resale.inbox.customer')" x-model="customer" required>
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected(old('customer_id', $prefill['customer_id']) === $customer->sqid)>{{ $customer->name }}</option>
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

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <x-input-field name="quantity" type="number" min="1" :max="max(1, $available)" :label="__('resale.transfer.quantity')" :value="old('quantity', (string) $quantityDefault)" required />
        <x-input-field name="sale_unit_price" type="number" step="0.01" min="0" :label="__('resale.field.sale_unit_price')" :value="old('sale_unit_price', $subscription->sale_unit_price?->withScale(2)->getAmount())" :hint="__('resale.dialog.price_hint')" />
    </div>
    <x-date-range layout="split" from-name="starts_on" to-name="ends_on" form-control size=""
                  :from-label="__('resale.field.starts_on')" :to-label="__('resale.field.ends_on')"
                  :from-required="true"
                  :from="old('starts_on', $prefill['starts_on'])"
                  :to="old('ends_on', $prefill['ends_on'])" />
    <x-input-field name="note" :label="__('resale.field.note')" :value="old('note')" />
</x-modal>
