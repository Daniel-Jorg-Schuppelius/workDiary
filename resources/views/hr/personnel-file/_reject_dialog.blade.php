{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reject_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einreichung ablehnen (MVP-987); der Grund ist für die einreichende Person sichtbar. Variablen: $submission
--}}
<x-modal
    :title="__('hr.personnel_file.action.reject')"
    :eyebrow="__('hr.personnel_file.title') . ' · ' . $submission->user?->name"
    icon="block"
    tone="error"
    :action="route('personnel-file.submissions.reject', $submission)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('hr.personnel_file.action.reject')">
    <p class="text-sm text-muted">{{ $submission->title }} · {{ $submission->original_name }}</p>
    <x-textarea-field name="review_note" :label="__('hr.personnel_file.field.review_note')" required minlength="3" maxlength="500" rows="3">{{ old('review_note') }}</x-textarea-field>
</x-modal>
