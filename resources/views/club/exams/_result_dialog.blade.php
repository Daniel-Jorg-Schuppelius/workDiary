{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _result_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ergebnis erfassen (in #entry-modal geladen). Variablen: $offer, $candidate, $results --}}
<x-modal
    :title="__('club.exams.action.result')"
    :eyebrow="$candidate->member?->fullName() . ' · ' . $candidate->targetGrade?->name"
    icon="grading"
    tone="primary"
    :action="route('club.exams.candidates.result', [$offer, $candidate])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.exams.field.result')" icon="grading" tone="primary" cols="1" :description="__('club.exams.hint.result')">
        <x-select-field name="result" :label="__('club.exams.field.result')" required>
            @foreach ($results as $result)
                <option value="{{ $result->value }}" @selected(old('result') === $result->value)>{{ $result->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" :value="old('note')" />
    </x-form-group>
</x-modal>
