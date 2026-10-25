{{--
  Created on   : Sun Oct 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _retention_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Einbehalt an der Eingangsrechnung anlegen (MVP-953). Erwartet: $incoming --}}
@php
    $gross = $incoming->amount_gross !== null
        ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($incoming->amount_gross->toFloat(), 2, withThousandsSeparator: true) . ' ' . $incoming->amount_gross->getCurrency()->value
        : null;
@endphp
<x-modal
    :title="__('sepa.retention.add')"
    :eyebrow="$incoming->invoice_number ?? $incoming->document?->title"
    icon="savings"
    :action="route('finance.incoming-invoices.retentions.store', $incoming)"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-muted">{{ __('sepa.retention.hint') }}</p>

    <div class="grid gap-x-3 md:grid-cols-2">
        <x-select-field name="kind" :label="__('sepa.retention.field.kind')" required>
            @foreach (\App\Enums\Invoicing\RetentionKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="due_on" type="date" :label="__('sepa.retention.field.due_on')" :value="old('due_on')" />
        <x-input-field name="percent" type="number" step="0.01" min="0.01" max="100"
                       :label="__('sepa.retention.field.percent')"
                       :value="old('percent')"
                       :hint="$gross !== null ? __('sepa.retention.percent_hint', ['amount' => $gross]) : null" />
        <x-input-field name="amount" type="number" step="0.01" min="0.01"
                       :label="__('sepa.retention.field.amount')"
                       :value="old('amount')"
                       :hint="__('sepa.retention.amount_hint')" />
        <x-input-field name="note" maxlength="500" span="2" :label="__('sepa.retention.field.note')" :value="old('note')" />
    </div>
</x-modal>
