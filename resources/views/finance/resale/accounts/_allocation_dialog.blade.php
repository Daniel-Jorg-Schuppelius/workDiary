{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _allocation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Aufteilung eines Lizenzkontos (Feature 185, MVP-1401): fremder Halter,
  Produkt, Lizenzen und Gültigkeit. Die Abtretungen je Vertrag leitet der
  AllocationDeriver daraus ab.
--}}
@php
    $mode = (string) old('mode', $allocation?->is_own_holding ? 'own' : ($allocation?->foreign_customer_id !== null ? 'foreign' : 'customer'));
    $customerSqid = $allocation?->customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, (int) $allocation->customer_id) : ($allocation?->foreignCustomer?->customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, (int) $allocation->foreignCustomer->customer_id) : '');
    $foreignSqid = $allocation?->foreign_customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer\ForeignCustomer::class, (int) $allocation->foreign_customer_id) : '';
@endphp
<x-modal
    :title="$allocation === null ? __('resale.account.allocation.add') : __('resale.account.allocation.edit')"
    icon="call_split"
    tone="primary"
    size="md"
    :action="$allocation === null ? route('finance.resale.accounts.allocations.store', $group->routeKey()) : route('finance.resale.accounts.allocations.update', $allocation->sqid)"
    :method="$allocation === null ? 'POST' : 'PUT'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.account.allocation.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.account.allocation.hint', ['account' => $group->name()]) }}</div>
    <p class="text-xs text-muted">{{ __('resale.account.allocation.rule') }}</p>

    <x-select-field name="product_key" :label="__('resale.account.allocation.product')" required>
        @foreach ($products as $key => $label)
            <option value="{{ $key }}" @selected((string) old('product_key', $allocation?->product_key) === $key)>{{ $label }}</option>
        @endforeach
    </x-select-field>

    <div class="space-y-3" x-data="resaleHolderPicker"
         data-map="{{ json_encode($foreignByCustomer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
         data-holder="{{ $mode }}"
         data-customer="{{ old('customer_id', $customerSqid) }}"
         data-foreign="{{ old('foreign_customer_id', $foreignSqid) }}">
        <x-select-field name="mode" :label="__('resale.account.allocation.holder')" x-model="holder">
            <option value="customer" @selected($mode === 'customer')>{{ __('resale.inbox.mode_customer') }}</option>
            <option value="foreign" @selected($mode === 'foreign')>{{ __('resale.inbox.mode_foreign') }}</option>
            <option value="own" @selected($mode === 'own')>{{ __('resale.holder.own') }}</option>
        </x-select-field>
        <div x-show="holder !== 'own'">
            <x-select-field name="customer_id" :label="__('resale.inbox.customer')" x-model="customer">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected(old('customer_id', $customerSqid) === $customer->sqid)>{{ $customer->name }}</option>
                @endforeach
            </x-select-field>
        </div>
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

    <x-input-field name="quantity" type="number" min="1" :label="__('resale.account.allocation.quantity')" :value="old('quantity', (string) ($allocation?->quantity ?? 1))" required />
    <x-date-range layout="split" from-name="valid_from" to-name="valid_until" form-control size=""
                  :from-label="__('resale.account.allocation.valid_from')" :to-label="__('resale.account.allocation.valid_until')"
                  :from-required="true"
                  :from="old('valid_from', $allocation?->valid_from?->toDateString() ?? $today->toDateString())"
                  :to="old('valid_until', $allocation?->valid_until?->toDateString() ?? '')" />
    <x-input-field name="note" :label="__('resale.field.note')" :value="old('note', $allocation?->note)" />
</x-modal>
