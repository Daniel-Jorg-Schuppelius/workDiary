{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _answer_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Status und Antwort der Organisation (Feature 206, MVP-1658). Variablen: $wish. --}}
@php
    $selectedStatus = (string) old('status', $wish->status->value);
@endphp
<x-modal
    :title="__('wishes.dialog.answer_title')"
    :eyebrow="__('wishes.title')"
    icon="reply"
    tone="primary"
    :action="route('wishes.answer.update', $wish)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('wishes.dialog.save')">

    <input type="hidden" name="_dialog_url" value="{{ route('wishes.answer.edit', $wish) . '?dialog=1' }}">

    <div class="mb-3 rounded-box bg-base-200 p-3 text-sm whitespace-pre-line">{{ $wish->body }}</div>

    <x-form-group :legend="__('wishes.dialog.answer_legend')" icon="reply" tone="primary">
        <x-select-field name="status" id="wish-status" :label="__('wishes.field.status')" required>
            <option value="{{ $wish->status->value }}" @selected($selectedStatus === $wish->status->value)>{{ $wish->status->label() }}</option>
            @foreach ($wish->status->allowedTransitions() as $status)
                <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="answer" id="wish-answer" :label="__('wishes.field.answer')" rows="4"
                          :value="old('answer', $wish->answer)" :hint="__('wishes.dialog.answer_hint')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
