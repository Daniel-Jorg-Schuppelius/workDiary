{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _unit_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lerneinheit-Dialog (Feature 149). Inhaltsblöcke folgen mit dem
  Autorenwerkzeug (MVP-736) — hier entsteht zunächst die Struktur.
  Variablen: $course (LearningCourse), $sections (Collection<LearningSection>)
--}}
<x-modal
    :title="__('learning.action.add_unit')"
    :eyebrow="$course->title"
    icon="playlist_add"
    tone="primary"
    :action="route('learning.courses.units.store', $course)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.add_unit')">

    <x-form-group :legend="__('learning.field.unit')" icon="playlist_add" tone="primary" cols="2">
        <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" span="2" :value="old('title')" />
        <x-select-field name="kind" :label="__('learning.field.unit_kind')" required>
            @foreach (\App\Enums\Learning\LearningUnitKind::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('kind', 'content') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="learning_section_id" :label="__('learning.field.section')">
            <option value="">{{ __('learning.field.no_section') }}</option>
            @foreach ($sections as $section)
                <option value="{{ $section->sqid }}" @selected((string) old('learning_section_id') === (string) $section->sqid)>{{ $section->title }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="duration_minutes" type="number" min="1" max="10000" :label="__('learning.field.duration_minutes')" :value="old('duration_minutes')" />
        <x-input-field name="points" type="number" min="0" max="1000" :label="__('learning.field.points')" :value="old('points', 0)" />
        <x-checkbox-field name="is_mandatory" :label="__('learning.field.is_mandatory')" :checked="(bool) old('is_mandatory', true)" span="2" />
    </x-form-group>
</x-modal>
