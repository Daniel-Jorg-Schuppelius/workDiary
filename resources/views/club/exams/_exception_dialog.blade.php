{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _exception_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ausnahmezulassung (in #entry-modal geladen). Variablen: $offer, $candidate --}}
<x-modal
    :title="__('club.exams.action.admit_exception')"
    :eyebrow="$candidate->member?->fullName()"
    icon="how_to_reg"
    tone="warning"
    :action="route('club.exams.candidates.admit', [$offer, $candidate])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.exams.action.admit')">

    <x-form-group :legend="__('club.exams.field.exception_reason')" icon="how_to_reg" tone="warning" cols="1" :description="__('club.exams.hint.exception')">
        <x-input-field name="exception_reason" :label="__('club.exams.field.exception_reason')" required maxlength="255" :value="old('exception_reason')" />
    </x-form-group>
</x-modal>
