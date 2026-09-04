{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Inbox-Zuordnung (Feature 152, MVP-759): eine Firma laut Anbieter wird
  Kunde, Fremdkunde eines Partners (neu angelegt), vorhandener Fremdkunde
  oder eigener Bestand — für alle ihre Abos ohne Halter, und gemerkt für
  den nächsten Import.
--}}
<x-modal
    :title="__('resale.inbox.assign_title', ['company' => $company])"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('finance.resale.inbox.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.inbox.assign_submit')"
>
    <input type="hidden" name="company" value="{{ $company }}">
    <div class="text-sm text-base-content/70">{{ trans_choice('resale.inbox.assign_hint', $count, ['count' => $count]) }}</div>

    <x-select-field name="mode" :label="__('resale.inbox.mode')" :hint="__('resale.inbox.mode_hint')">
        <option value="customer" @selected(old('mode', 'customer') === 'customer')>{{ __('resale.inbox.mode_customer') }}</option>
        <option value="partner" @selected(old('mode') === 'partner')>{{ __('resale.inbox.mode_partner') }}</option>
        <option value="foreign" @selected(old('mode') === 'foreign')>{{ __('resale.inbox.mode_foreign') }}</option>
        <option value="own" @selected(old('mode') === 'own')>{{ __('resale.holder.own') }}</option>
    </x-select-field>

    <x-select-field name="customer_id" :label="__('resale.inbox.customer')">
        <option value="">—</option>
        @foreach ($customers as $customer)
            <option value="{{ $customer->sqid }}" @selected(old('customer_id') === $customer->sqid)>{{ $customer->name }}</option>
        @endforeach
    </x-select-field>

    <x-select-field name="foreign_customer_id" :label="__('resale.inbox.foreign')">
        <option value="">—</option>
        @foreach ($foreignCustomers as $foreign)
            <option value="{{ $foreign->sqid }}" @selected(old('foreign_customer_id') === $foreign->sqid)>{{ $foreign->name }} ({{ $foreign->customer?->name }})</option>
        @endforeach
    </x-select-field>
</x-modal>
