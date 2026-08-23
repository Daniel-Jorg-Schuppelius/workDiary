{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _clearing_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bankumsatz auf ein Klärungskonto buchen (Feature 125, MVP-681). Notiz und
  Wiedervorlage sind Pflicht: Ein Klärungskonto ohne beides wird zum
  Auffangbecken, das erst beim Abschluss auffällt.
--}}
<x-modal
    :title="__('accounting.clearing.action.post')"
    icon="help_center"
    :action="route('finance.accounting.inbox.clearing.store', $transaction->sqid)"
    method="POST"
    :submit-label="__('accounting.clearing.action.post_submit')"
>
    <p class="text-sm text-base-content/70">
        {{ $transaction->booking_date->fdate() }} ·
        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transaction->amount, 2, withThousandsSeparator: true) }}
        {{ $transaction->currency->value }}
        @if ($transaction->purpose)
            · {{ \Illuminate\Support\Str::limit($transaction->purpose, 90) }}
        @endif
    </p>

    @if ($accounts->isEmpty())
        <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
            <x-icon name="warning" />
            <span>{{ __('accounting.clearing.no_account') }}</span>
        </div>
    @else
        <x-select-field name="clearing_account" :label="__('accounting.clearing.field.account')"
                        :hint="__('accounting.clearing.hint.account')">
            @foreach ($accounts as $account)
                <option value="{{ $account->sqid }}">{{ $account->displayLabel() }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="note" type="text" required minlength="5" maxlength="500"
                       :label="__('accounting.clearing.field.note')"
                       :hint="__('accounting.clearing.hint.note')" />

        <x-input-field name="follow_up_on" type="date" required
                       :label="__('accounting.clearing.field.follow_up_on')"
                       :hint="__('accounting.clearing.hint.follow_up_on')"
                       :value="old('follow_up_on', now()->addWeeks(2)->toDateString())" />
    @endif
</x-modal>
