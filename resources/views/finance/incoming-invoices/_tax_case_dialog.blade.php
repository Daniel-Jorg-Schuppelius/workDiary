{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tax_case_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Steuerfall einer Eingangsrechnung (MVP-1621). Leer = aus Beleg und Lieferant. --}}
<x-modal
    :title="__('invoicing.incoming_tax_case.title')"
    :eyebrow="$incoming->document?->title"
    icon="sync_alt"
    :action="route('finance.incoming-invoices.tax-case', $incoming)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">{{ __('invoicing.incoming_tax_case.hint') }}</p>

    @if ($booked)
        <div class="alert bg-warning/10 border-warning/30 text-sm" role="note">
            <x-icon name="warning" />
            <span>{{ __('invoicing.incoming_tax_case.error.booked') }}</span>
        </div>
    @endif

    <x-select-field name="tax_case" :label="__('invoicing.incoming_tax_case.field')"
                    :hint="__('invoicing.incoming_tax_case.detected', ['case' => $resolved['case']->label(), 'source' => __('invoicing.incoming_tax_case.source.' . $resolved['source'])])">
        <option value="">{{ __('invoicing.incoming_tax_case.automatic') }}</option>
        @foreach ($cases as $case)
            <option value="{{ $case->value }}" @selected(old('tax_case', $incoming->tax_case?->value ?? '') === $case->value)>{{ $case->label() }}</option>
        @endforeach
    </x-select-field>
</x-modal>
