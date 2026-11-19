{{--
  Created on   : Wed Nov 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_start_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Abrechnungsbeginn des Portalkontos übernehmen (MVP-1746/1747): für die offenen
  Lizenzjahre, die vor dem Kontobeginn begannen, gesammelt entscheiden —
  verzichten oder noch selbst berechnen.
  Erwartet: $customer, $groups (ProviderBillingStart::candidates).
--}}
<x-modal
    :title="__('resale.billing_start.dialog_title', ['customer' => $customer->name])"
    icon="event_available"
    tone="info"
    size="md"
    :action="route('finance.resale.reconcile.billing-start.store', $customer)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.billing_start.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.billing_start.dialog_hint') }}</div>
    @forelse ($groups as $group)
        <div class="text-sm font-medium">{{ __('resale.billing_start.title', ['account' => $group['account'], 'date' => $group['since']->fdate()]) }}</div>
        <ul class="text-sm max-h-64 overflow-y-auto space-y-0.5 border border-base-300 rounded-box px-3 py-2">
            @foreach ($group['periods'] as $period)
                <li class="flex flex-wrap justify-between gap-2">
                    <span>{{ $period->subscription->label }}</span>
                    <span class="tabular-nums text-muted">{{ $period->label() }}</span>
                </li>
            @endforeach
        </ul>
    @empty
        <x-empty-state compact icon="event_available" :title="__('resale.billing_start.empty')" />
    @endforelse
    @if ($groups !== [])
        <x-select-field name="decision" :label="__('resale.billing_start.decision')" required :hint="__('resale.billing_start.decision_hint')">
            <option value="waive" @selected(old('decision', 'waive') === 'waive')>{{ __('resale.billing_start.decision_waive') }}</option>
            <option value="bill" @selected(old('decision') === 'bill')>{{ __('resale.billing_start.decision_bill') }}</option>
        </x-select-field>
        <x-input-field name="note" :label="__('resale.billing_start.note')" :value="old('note')" maxlength="120" :hint="__('resale.billing_start.note_hint')" />
    @endif
</x-modal>
