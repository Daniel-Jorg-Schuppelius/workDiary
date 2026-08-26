{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _version_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog für eine neue Kursversion (Feature 145). Variablen: $course
--}}
<x-modal
    :title="__('training.action.create_version')"
    :eyebrow="$course->title"
    icon="history_edu"
    tone="primary"
    :action="route('training.courses.versions.store', $course)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('training.action.create_version')">

    <x-form-group :legend="__('training.field.versions')" icon="history_edu" tone="primary" cols="2">
        <x-input-field name="label" :label="__('training.field.version_label')" maxlength="60" :value="old('label')" />
        <x-input-field name="valid_from" type="date" :label="__('training.field.valid_from')" :value="old('valid_from')" />
        <x-textarea-field name="content_summary" :label="__('training.field.content_summary')" rows="3" maxlength="5000" span="2" :value="old('content_summary')" />
    </x-form-group>
</x-modal>
