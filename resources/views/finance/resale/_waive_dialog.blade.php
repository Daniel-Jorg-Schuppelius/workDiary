{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _waive_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Periode bewusst nicht berechnen (Kulanz, Test, eigener Bestand) oder als
  strittig markieren — mit Grund (Feature 152, MVP-761).
--}}
<x-modal
    :title="__('resale.link.waive_title', ['period' => $period->label()])"
    icon="do_not_disturb_on"
    tone="warning"
    size="md"
    :action="route('finance.resale.periods.waive', $period->sqid)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.link.waive_submit')"
>
    <div class="text-sm text-base-content/70">{{ $period->subscription->label }} · {{ $period->subscription->holderLabel() }}</div>
    <x-select-field name="decision" :label="__('resale.link.decision')">
        <option value="waived" @selected(old('decision', 'waived') === 'waived')>{{ __('resale.period_status.waived') }}</option>
        <option value="disputed" @selected(old('decision') === 'disputed')>{{ __('resale.period_status.disputed') }}</option>
    </x-select-field>
    <x-input-field name="reason" :label="__('resale.link.reason')" :value="old('reason')" required :hint="__('resale.link.reason_hint')" />
</x-modal>
