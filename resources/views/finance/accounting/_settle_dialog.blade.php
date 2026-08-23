{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settle_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Manueller Ausgleich (Feature 125, MVP-674) — nur für Fälle ohne Geldfluss:
  Skonto, Einbehalt, Ausbuchung. Zahlungen kommen aus dem Zahlungsabgleich.
--}}
<x-modal
    :title="__('accounting.open_items.action.settle')"
    :eyebrow="$item->document_reference"
    icon="playlist_add_check"
    :action="route('finance.accounting.open-items.settle', $item)"
    method="POST"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">
        {{ __('accounting.open_items.settle_hint', ['open' => $item->open_amount?->format() ?? '—']) }}
    </p>

    <x-select-field name="kind" :label="__('accounting.open_items.column.kind')">
        @foreach ($kinds as $kind)
            <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
    </x-select-field>

    <x-input-field name="amount" type="number" step="0.01" min="0.01" required
                   :label="__('accounting.ledger.column.amount')"
                   :value="old('amount', '')" />

    <x-input-field name="note" type="text" maxlength="191"
                   :label="__('accounting.ledger.field.note')"
                   :value="old('note', '')" />
</x-modal>
