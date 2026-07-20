{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-414: Bareinnahme/-ausgabe. Variablen: $register, $openInvoices --}}
<x-modal
    :title="__('Buchung erfassen')"
    :eyebrow="$register->name"
    icon="point_of_sale"
    tone="primary"
    :action="route('cash-registers.entries.store', $register)"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Buchen')"
    size="md">

    <x-form-group :legend="__('Buchung')" icon="payments" tone="primary" cols="2">
        <x-input-field name="booked_on" type="date" :label="__('Datum')" required :value="old('booked_on', now()->format('Y-m-d'))" />
        <x-select-field name="direction" :label="__('Richtung')" required>
            <option value="in" @selected(old('direction', 'in') === 'in')>{{ __('Einnahme') }}</option>
            <option value="out" @selected(old('direction') === 'out')>{{ __('Ausgabe') }}</option>
        </x-select-field>
        <x-input-field name="amount" type="number" :label="__('Betrag (EUR)')" required min="0.01" step="0.01" :value="old('amount', '')" />
        <x-input-field name="tax_rate" type="number" :label="__('USt-Satz % (informativ)')" min="0" max="99.99" step="0.01" :value="old('tax_rate', '')" />
        <x-input-field name="purpose" :label="__('Zweck / Belegtext')" required maxlength="500" span="2" :value="old('purpose', '')" />
        <x-input-field name="counterparty" :label="__('Gegenpartei')" maxlength="180" :value="old('counterparty', '')" />
        <x-input-field name="receipt" type="file" :label="__('Beleg (optional)')" span="2"
            :hint="__('Max. :mb MB — der Beleg ist nach dem Buchen nicht mehr löschbar (GoBD).', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()])" />
        <x-select-field name="invoice_id" :label="__('Barzahlung zu Rechnung (optional)')" :hint="__('Volle Deckung setzt die Rechnung auf bezahlt.')">
            <option value="">{{ __('— keine —') }}</option>
            @foreach ($openInvoices as $invoice)
                @php($isqid = \App\Support\Sqid::encode(\App\Models\Invoice::class, (int) $invoice->id))
                <option value="{{ $isqid }}" @selected(old('invoice_id') === $isqid)>{{ $invoice->number }} ({{ number_format((float) $invoice->total, 2, ',', '.') }})</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
