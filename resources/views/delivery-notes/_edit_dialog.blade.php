{{--
  Created on   : Tue Oct 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _edit_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lieferschein-Entwurf: Mengen (Teillieferung), Lieferdatum, Hinweis (MVP-1163). Variablen: $note, $lines --}}
<x-modal
    :title="__('billing.delivery_note.edit')"
    :eyebrow="$note->number"
    icon="local_shipping"
    tone="primary"
    :action="route('delivery-notes.update', $note)"
    method="PUT"
    :submit-label="__('Speichern')"
    size="md">
    <x-form-group :legend="__('Positionen')" icon="list" tone="primary" cols="1">
        <p class="text-xs text-muted">{{ __('billing.delivery_note.edit_hint') }}</p>
        @foreach ($lines as $line)
            <x-input-field :name="'quantities[' . $line->sqid . ']'" type="number" min="0" step="0.0001"
                           :label="$line->description . ($line->unit ? ' (' . $line->unit . ')' : '')"
                           :value="old('quantities.' . $line->sqid, \CommonToolkit\Helper\Data\NumberHelper::trimTrailingZeros((string) $line->quantity))" required />
        @endforeach
    </x-form-group>
    <x-form-group :legend="__('billing.delivery_note.details')" icon="event" tone="primary" cols="1">
        <x-input-field name="delivered_on" type="date" :label="__('manufacturing.delivery_note.date')" required
                       :value="old('delivered_on', $note->delivered_on?->toDateString())" />
        <x-textarea-field name="note" :label="__('Notiz')" rows="3" maxlength="2000" :value="old('note', $note->note)" />
    </x-form-group>
    <x-validation-errors />
</x-modal>
