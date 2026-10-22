{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transmission_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Übermittlungsschritt eines exportierten DATEV-Stapels erfassen (MVP-1277).
  Angeboten werden nur die erlaubten Folgezustände. Variablen: $batch,
  $current, $targets.
--}}
<x-modal
    :title="__('finance.datev.dialog.transmission_title')"
    :eyebrow="'#' . $batch->batch_no"
    icon="send"
    tone="primary"
    :action="route('finance.datev.transmission', $batch)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('finance.datev.action.transmission')">

    <p class="text-sm">{{ __('finance.datev.dialog.transmission_hint') }}</p>
    <x-select-field name="status" :label="__('finance.datev.field.transmission')" required>
        @foreach ($targets as $target)
            <option value="{{ $target->value }}" @selected(old('status') === $target->value)>{{ $target->label() }}</option>
        @endforeach
    </x-select-field>
    <x-textarea-field name="note" :label="__('finance.datev.field.transmission_note')" maxlength="500" rows="2" :value="old('note')" />
</x-modal>
