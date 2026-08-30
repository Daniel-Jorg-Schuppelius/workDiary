{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _section_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abschnitts-Dialog (Feature 149). Variablen: $course (LearningCourse)
--}}
<x-modal
    :title="__('learning.action.add_section')"
    :eyebrow="$course->title"
    icon="segment"
    tone="primary"
    :action="route('learning.courses.sections.store', $course)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.add_section')">

    <x-form-group :legend="__('learning.field.section')" icon="segment" tone="primary" cols="1">
        <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" :value="old('title')" />
        <x-textarea-field name="description" :label="__('learning.field.description')" rows="3" maxlength="2000" :value="old('description')" />
    </x-form-group>
</x-modal>
