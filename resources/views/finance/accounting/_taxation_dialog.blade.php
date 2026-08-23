{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _taxation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Versteuerungsart wechseln (Feature 125, MVP-679). Der Wechsel wird nicht
  blockiert — § 20 S. 3 UStG verlangt eine fachliche Beurteilung der offenen
  Posten, keine technische Sperre. Das Programm zeigt, welche betroffen sind.
--}}
<x-modal
    :title="__('accounting.taxation.action.switch')"
    icon="swap_vert"
    :action="route('finance.accounting.taxation')"
    method="POST"
    :submit-label="__('accounting.taxation.action.switch_submit')"
>
    <p class="text-sm text-base-content/70">
        {{ __('accounting.taxation.current', ['method' => $current->label()]) }}
    </p>

    <x-select-field name="method" :label="__('accounting.taxation.field.method')" :hint="__('accounting.taxation.hint.method')">
        @foreach ($methods as $method)
            <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="valid_from" type="date" required
                   :label="__('accounting.taxation.field.valid_from')"
                   :hint="__('accounting.taxation.hint.valid_from')"
                   :value="old('valid_from', $suggestedFrom)" />

    <x-input-field name="reason" type="text" maxlength="500"
                   :label="__('accounting.ledger.field.note')"
                   :value="old('reason', '')" />

    @if ($changeover['count'] > 0)
        <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
            <x-icon name="warning" />
            <div>
                <div class="font-medium">{{ __('accounting.taxation.changeover.headline', ['count' => $changeover['count'], 'amount' => $changeover['open_amount']]) }}</div>
                <p class="text-xs">{{ __('accounting.taxation.changeover.hint') }}</p>
            </div>
        </div>
    @endif
</x-modal>
