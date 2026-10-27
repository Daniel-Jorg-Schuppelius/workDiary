{{--
  Created on   : Tue Oct 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bulk_assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sammelzuordnung des Halters aus der Abo-Liste (Feature 152, Nachbesserung
  2026-10-27): ein Halter für alle gewählten Abos — auch für eines (Zeilen-
  aktion). Domains bekommen ihn auch in der Domainverwaltung; Firmen laut
  Anbieter werden auf Wunsch für den nächsten Import gemerkt.
--}}
@php
    $mode = (string) old('mode', 'customer');
    $preset = (string) old('customer_id', $suggestions[0]['customer']->sqid ?? '');
@endphp
<x-modal
    :title="trans_choice('resale.bulk.assign_title', $subscriptions->count(), ['count' => $subscriptions->count()])"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('finance.resale.subscriptions.bulk.assign')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.bulk.assign_submit')"
>
    @foreach ($subscriptions as $subscription)
        <input type="hidden" name="ids[]" value="{{ $subscription->sqid }}">
    @endforeach
    <div class="text-sm text-base-content/70">{{ __('resale.bulk.assign_hint') }}</div>
    <ul class="text-sm max-h-48 overflow-y-auto space-y-0.5 border border-base-300 rounded-box px-3 py-2">
        @foreach ($subscriptions as $subscription)
            <li class="flex flex-wrap justify-between gap-2">
                <span>{{ $subscription->label }}<span class="text-xs text-muted"> · {{ $subscription->provider->label() }}@if (trim((string) $subscription->company_name) !== '') · {{ $subscription->company_name }}@endif</span></span>
                <span class="text-xs text-muted">{{ $subscription->hasHolder() ? $subscription->holderLabel() : __('resale.holder.unassigned') }}</span>
            </li>
        @endforeach
    </ul>
    @if ($suggestions !== [])
        <div class="text-xs">
            <span class="text-muted">{{ __('resale.bulk.suggestion') }}:</span>
            @foreach ($suggestions as $suggestion)
                <x-status-badge tone="plain" outline class="mr-1">{{ $suggestion['customer']->name }} · {{ __('domain.mapping.reason_' . $suggestion['reason']) }}</x-status-badge>
            @endforeach
        </div>
    @endif

    {{-- Fremdkunden-Auswahl nur, wenn der gewählte Kunde Fremdkunden hat (resaleHolderPicker, wie der Inbox-Dialog). --}}
    <div class="space-y-3" x-data="resaleHolderPicker"
         data-map="{{ json_encode($foreignByCustomer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
         data-holder="{{ $mode }}"
         data-customer="{{ $preset }}"
         data-foreign="{{ old('foreign_customer_id', '') }}">
        <x-select-field name="mode" :label="__('resale.inbox.mode')" x-model="holder">
            <option value="customer" @selected($mode === 'customer')>{{ __('resale.inbox.mode_customer') }}</option>
            @if ($withCompany > 0)
                <option value="partner" @selected($mode === 'partner')>{{ __('resale.inbox.mode_partner') }}</option>
            @endif
            <option value="foreign" @selected($mode === 'foreign')>{{ __('resale.inbox.mode_foreign') }}</option>
            <option value="own" @selected($mode === 'own')>{{ __('resale.holder.own') }}</option>
        </x-select-field>
        @if ($withCompany > 0)
            <p class="text-xs text-muted" x-show="holder === 'partner'">{{ __('resale.bulk.partner_hint') }}</p>
        @endif

        <div x-show="needsCustomer()">
            <x-select-field name="customer_id" :label="__('resale.inbox.customer')" x-model="customer">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected($preset === $customer->sqid)>{{ $customer->name }}</option>
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

        @if ($withCompany > 0)
            <x-checkbox-field name="remember" :label="__('resale.bulk.remember')" :checked="(bool) old('remember', true)" :hint="__('resale.bulk.remember_hint')" />
        @endif
    </div>
</x-modal>
