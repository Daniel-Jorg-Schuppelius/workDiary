{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _vat_extension_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dauerfristverlängerung erfassen (Feature 125, MVP-684). Die Verlängerung
  gilt dauerhaft weiter; die Sondervorauszahlung ist jedes Jahr neu — deshalb
  eine Zeile je Kalenderjahr.
--}}
<x-modal
    :title="__('accounting.filing.extension.title')"
    icon="more_time"
    :action="route('finance.accounting.vat-extension')"
    method="POST"
    :submit-label="__('Speichern')"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="year" type="number" min="2000" max="2100" required
                       :label="__('accounting.filing.field.year')"
                       :value="old('year', $extension->year ?? $year)" />
        <x-input-field name="granted_on" type="date"
                       :label="__('accounting.filing.field.granted_on')"
                       :hint="__('accounting.filing.hint.granted_on')"
                       :value="old('granted_on', $extension?->granted_on?->toDateString())" />
    </div>

    @if ($interval->requiresSpecialPrepayment())
        <x-input-field name="special_prepayment_amount" type="number" step="0.01" min="0"
                       :label="__('accounting.filing.field.special_prepayment')"
                       :hint="__('accounting.filing.hint.special_prepayment')"
                       :value="old('special_prepayment_amount', $extension?->special_prepayment_amount?->getAmount())" />
    @else
        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.filing.extension.no_prepayment') }}</span>
        </div>
    @endif

    <x-input-field name="note" type="text" maxlength="500"
                   :label="__('accounting.ledger.field.note')"
                   :value="old('note', $extension->note ?? '')" />
</x-modal>
