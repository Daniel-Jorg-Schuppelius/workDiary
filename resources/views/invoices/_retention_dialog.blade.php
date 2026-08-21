{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _retention_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sicherheitseinbehalt hinterlegen (Feature 113, MVP-602). Nur am Entwurf —
  der Einbehalt ist Beleginhalt und wird mit dem Ausstellen eingefroren.
--}}
<x-modal
    :title="__('invoicing.retention.dialog_title')"
    :eyebrow="$invoice->number"
    icon="savings"
    :action="route('invoices.retentions.store', $invoice)"
    method="POST"
    :submit-label="__('invoicing.retention.submit')"
>
    <p class="text-sm text-base-content/70">{{ __('invoicing.retention.dialog_hint') }}</p>

    <div>
        <label class="label" for="retention-kind"><span class="label-text">{{ __('invoicing.retention.kind') }}</span></label>
        <select id="retention-kind" name="kind" class="select select-bordered w-full">
            @foreach (\App\Enums\Invoicing\RetentionKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label" for="retention-basis"><span class="label-text">{{ __('invoicing.retention.basis') }}</span></label>
        <select id="retention-basis" name="basis" class="select select-bordered w-full">
            <option value="percent" @selected(old('basis', 'percent') === 'percent')>{{ __('invoicing.retention.basis_percent') }}</option>
            <option value="amount" @selected(old('basis') === 'amount')>{{ __('invoicing.retention.basis_amount') }}</option>
        </select>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="percent" type="number" step="0.01" min="0.01" max="100"
                       :label="__('invoicing.retention.percent')"
                       :value="old('percent', '5.00')" />

    {{-- Bemessungsgrundlage: „5 % der Nettosumme" und „5 % der
         Rechnungssumme" sind zwei verschiedene Beträge; welche gilt, steht
         im Vertrag und gehört deshalb an den einzelnen Einbehalt. --}}
    <div>
        <label class="label" for="retention-base-kind"><span class="label-text">{{ __('invoicing.retention.base_kind') }}</span></label>
        <select id="retention-base-kind" name="base_kind" class="select select-bordered w-full">
            @foreach (\App\Enums\Invoicing\RetentionBase::cases() as $base)
                <option value="{{ $base->value }}" @selected(old('base_kind', \App\Enums\Invoicing\RetentionBase::Net->value) === $base->value)>{{ $base->label() }}</option>
            @endforeach
        </select>
    </div>
        <x-input-field name="amount" type="number" step="0.01" min="0.01"
                       :label="__('invoicing.retention.amount')"
                       :value="old('amount', '')" />
    </div>

    <x-input-field name="due_on" type="date"
                   :label="__('invoicing.retention.due_on')"
                   :value="old('due_on', '')"
                   :hint="__('invoicing.retention.due_on_hint')" />

    <x-input-field name="note" type="text" maxlength="500"
                   :label="__('invoicing.retention.note')"
                   :value="old('note', '')" />
</x-modal>
