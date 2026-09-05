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

    {{-- Fremdkunden-Auswahl nur, wenn der gewählte Kunde Fremdkunden hat (resaleHolderPicker). --}}
    <div class="space-y-3" x-data="resaleHolderPicker(@js($foreignByCustomer), @js(old('mode', 'customer')), @js(old('customer_id', '')), @js(old('foreign_customer_id', '')))">
        <x-select-field name="mode" :label="__('resale.inbox.mode')" :hint="__('resale.inbox.mode_hint')" x-model="holder">
            <option value="customer">{{ __('resale.inbox.mode_customer') }}</option>
            <option value="partner">{{ __('resale.inbox.mode_partner') }}</option>
            <option value="foreign">{{ __('resale.inbox.mode_foreign') }}</option>
            <option value="own">{{ __('resale.holder.own') }}</option>
        </x-select-field>

        <div x-show="needsCustomer()" x-cloak>
            <x-select-field name="customer_id" :label="__('resale.inbox.customer')" x-model="customer">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}">{{ $customer->name }}</option>
                @endforeach
            </x-select-field>
        </div>

        <div x-show="showForeign()" x-cloak>
            <x-select-field name="foreign_customer_id" :label="__('resale.inbox.foreign')" x-model="foreign">
                <option value="">—</option>
                <template x-for="fc in options()" :key="fc.sqid">
                    <option :value="fc.sqid" x-text="fc.name"></option>
                </template>
            </x-select-field>
        </div>
        <p class="text-xs text-warning" x-show="noForeign()" x-cloak>{{ __('resale.dialog.no_foreign_customers') }}</p>
    </div>
</x-modal>
