{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _bulk_waive_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mehrere Perioden mit einem Grund verzichten oder strittig stellen
  (Feature 185, MVP-1415). Erwartet: $periods (mit subscription).
--}}
<x-modal
    :title="trans_choice('resale.bulk.waive_title', $periods->count(), ['count' => $periods->count()])"
    icon="do_not_disturb_on"
    tone="warning"
    size="md"
    :action="route('finance.resale.periods.bulk.waive')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.link.waive_submit')"
>
    @foreach ($periods as $period)
        <input type="hidden" name="ids[]" value="{{ $period->sqid }}">
    @endforeach
    <div class="text-sm text-base-content/70">{{ __('resale.bulk.waive_hint') }}</div>
    <ul class="text-sm max-h-48 overflow-y-auto space-y-0.5 border border-base-300 rounded-box px-3 py-2">
        @foreach ($periods as $period)
            <li class="flex flex-wrap justify-between gap-2">
                <span>{{ $period->subscription->label }} · {{ $period->subscription->holderLabel() }}</span>
                <span class="tabular-nums text-muted">{{ $period->label() }}</span>
            </li>
        @endforeach
    </ul>
    <x-select-field name="decision" :label="__('resale.link.decision')">
        <option value="waived" @selected(old('decision', 'waived') === 'waived')>{{ __('resale.period_status.waived') }}</option>
        <option value="disputed" @selected(old('decision') === 'disputed')>{{ __('resale.period_status.disputed') }}</option>
    </x-select-field>
    <x-input-field name="reason" :label="__('resale.link.reason')" :value="old('reason')" required :hint="__('resale.link.reason_hint')" />
</x-modal>
