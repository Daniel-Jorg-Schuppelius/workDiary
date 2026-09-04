{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _draft_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rechnungsentwurf nach Lexoffice (Feature 152, MVP-764): Rechnungsempfänger
  mit offenen Perioden wählen; eine Position je Abo und Zeitraum.
--}}
@php
    $money = static fn(float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
@endphp
<x-modal
    :title="__('resale.draft.dialog_title')"
    icon="receipt_long"
    tone="primary"
    size="md"
    :action="route('finance.resale.periods.draft.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.draft.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.draft.hint') }}</div>
    @if ($recipients === [])
        <div class="alert alert-info text-sm"><span>{{ __('resale.draft.error.nothing_open') }}</span></div>
    @endif
    <x-select-field name="customer_id" :label="__('resale.field.billed_to')" required>
        <option value="">—</option>
        @foreach ($recipients as $row)
            <option value="{{ $row['customer']->sqid }}" @selected(old('customer_id') === $row['customer']->sqid)>
                {{ $row['customer']->name }} · {{ trans_choice('resale.periods.open_count', $row['periods'], ['count' => $row['periods']]) }} · {{ $money($row['net']) }}
            </option>
        @endforeach
    </x-select-field>
</x-modal>
