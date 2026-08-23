{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _filing_interval_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Voranmeldungszeitraum wechseln (Feature 125, MVP-684). Der Vorschlag aus der
  Vorjahressteuer steht daneben — angewendet wird er nie: Über den Zeitraum
  entscheidet das Finanzamt.
--}}
<x-modal
    :title="__('accounting.filing.action.switch')"
    icon="event_repeat"
    :action="route('finance.accounting.filing-interval')"
    method="POST"
    :submit-label="__('accounting.filing.action.switch_submit')"
>
    <p class="text-sm text-base-content/70">
        {{ __('accounting.filing.current', ['interval' => $current->label()]) }}
    </p>

    <x-select-field name="interval" :label="__('accounting.filing.field.interval')" :hint="__('accounting.filing.hint.interval')">
        @foreach ($intervals as $interval)
            <option value="{{ $interval->value }}" @selected(old('interval', $current->value) === $interval->value)>{{ $interval->label() }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="valid_from" type="date" required
                   :label="__('accounting.filing.field.valid_from')"
                   :hint="__('accounting.filing.hint.valid_from')"
                   :value="old('valid_from', $suggestedFrom)" />

    <x-input-field name="reason" type="text" maxlength="500"
                   :label="__('accounting.ledger.field.note')"
                   :value="old('reason', '')" />

    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="info" />
        <div>
            <div class="font-medium">
                {{ __('accounting.filing.suggestion.headline', [
                    'interval' => $suggestion['interval']->label(),
                    'year' => $suggestion['prior_year'],
                    'amount' => $suggestion['prior_year_tax'],
                ]) }}
            </div>
            <p class="text-xs">{{ __($suggestion['reason_key']) }}</p>
            @if ($suggestion['founder_rule_active'])
                <p class="text-xs">{{ __('accounting.filing.suggestion.founder_rule') }}</p>
            @endif
        </div>
    </div>
</x-modal>
