{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fill_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Punkt ausfüllen (MVP-883). Eingabe über x-field-input, die
     Rückabbildung auf value_json übernimmt ProtocolItemFields::fromInput(). --}}
<x-modal
    :title="__('protocol.action.fillItem')"
    :eyebrow="$item->label"
    icon="edit_note"
    tone="primary"
    :action="route('protocols.items.fill', $item)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.action.fillItem')">
    @if ($item->description)
        <p class="text-sm text-muted">{{ $item->description }}</p>
    @endif
    @if ($fillable)
        <x-field-input :field="$field" :value="$value" />
    @else
        <p class="text-sm text-muted">{{ __('protocol.dialog.not_fillable') }}</p>
    @endif
    <x-select-field name="result" :label="__('protocol.dialog.result')" :hint="__('protocol.dialog.result_hint')">
        <option value="">—</option>
        @foreach (\App\Enums\Protocol\ProtocolItemResult::cases() as $result)
            <option value="{{ $result->value }}" @selected(old('result', $item->result?->value) === $result->value)>{{ $result->label() }}</option>
        @endforeach
    </x-select-field>
    <x-textarea-field name="note" :label="__('protocol.dialog.note')" :value="old('note', $item->note)" rows="2" />
</x-modal>
