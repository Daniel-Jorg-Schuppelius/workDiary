{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kurs-Dialog (Feature 149). Variablen: $course (LearningCourse|null),
  $trainingCourses (Collection<TrainingCourse>)
  $assets (Collection<Asset>)
--}}
@php
    $isEdit = $course !== null;
    $selectedAudiences = old('audiences', $course?->audiences ?? [\App\Enums\Learning\LearningAudience::Internal->value]);
@endphp

<x-modal
    :title="$isEdit ? __('learning.action.edit') : __('learning.action.create')"
    :eyebrow="__('learning.title.courses')"
    icon="school"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('learning.courses.update', $course) : route('learning.courses.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('learning.action.save') : __('learning.action.create')">

    <x-form-group :legend="__('learning.field.course')" icon="school" tone="primary" cols="2">
        <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" span="2" :value="old('title', $course?->title)" />
        @unless ($isEdit)
            <x-input-field name="code" :label="__('learning.field.code')" maxlength="60" :value="old('code')" />
        @endunless
        <x-input-field name="subtitle" :label="__('learning.field.subtitle')" maxlength="255" :value="old('subtitle', $course?->subtitle)" />
        <x-textarea-field name="description" :label="__('learning.field.description')" rows="3" span="2" maxlength="5000" :value="old('description', $course?->description)" />
        <x-textarea-field name="objectives" :label="__('learning.field.objectives')" rows="2" span="2" maxlength="5000" :value="old('objectives', $course?->objectives)" />
        <x-input-field name="duration_minutes" type="number" min="1" max="10000" :label="__('learning.field.duration_minutes')" :value="old('duration_minutes', $course?->duration_minutes)" />
        <x-input-field name="validity_months" type="number" min="1" max="600" :label="__('learning.field.validity_months')" :value="old('validity_months', $course?->validity_months)" />
    </x-form-group>

    <x-form-group :legend="__('learning.field.access')" icon="group" tone="info" cols="2">
        <x-select-field name="access_kind" :label="__('learning.field.access_kind')" required>
            @foreach (\App\Enums\Learning\LearningAccessKind::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('access_kind', $course?->access_kind?->value ?? 'enrolled') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="training_course_id" :label="__('learning.field.training_course')" :help="__('learning.help.training_course')">
            <option value="">{{ __('learning.field.no_training_course') }}</option>
            @foreach ($trainingCourses as $trainingCourse)
                <option value="{{ $trainingCourse->sqid }}" @selected((string) old('training_course_id', $course?->trainingCourse?->sqid) === (string) $trainingCourse->sqid)>{{ $trainingCourse->title }}</option>
            @endforeach
        </x-select-field>
        {{-- Geräteeinweisung (MVP-740): der Nachweis trägt das Gerät mit,
             damit dokumentiert ist, WORAN unterwiesen wurde. Gesperrt wird
             dadurch nichts — das bleibt beim Asset-Sperrmodell. --}}
        <x-select-field name="asset_id" :label="__('learning.field.asset')" :help="__('learning.help.asset')">
            <option value="">{{ __('learning.field.no_asset') }}</option>
            @foreach ($assets as $asset)
                <option value="{{ $asset->sqid }}" @selected((string) old('asset_id', $course?->asset?->sqid) === (string) $asset->sqid)>{{ $asset->name }}</option>
            @endforeach
        </x-select-field>
        {{-- Zielgruppen steuern nur die Sichtbarkeit im Katalog; ohne
             passende Zielgruppe bleibt ein Kurs extern unsichtbar (Default-Deny). --}}
        @foreach (\App\Enums\Learning\LearningAudience::cases() as $case)
            <x-checkbox-field name="audiences[]" :id="'audience-' . $case->value" :value="$case->value"
                              :label="$case->label()" :toggle="false" :with-hidden="false"
                              :checked="in_array($case->value, (array) $selectedAudiences, true)" />
        @endforeach
    </x-form-group>

    {{-- Zeitpolitik: § 12 Abs. 1 ArbSchG verlangt Unterweisung „während der
         Arbeitszeit" — deshalb steht die Regel am Kurs, nicht global. --}}
    <x-form-group :legend="__('learning.field.time_and_proof')" icon="schedule" tone="warning" cols="2">
        <x-select-field name="time_policy" :label="__('learning.field.time_policy')" required :help="__('learning.help.time_policy')">
            @foreach (\App\Enums\Learning\LearningTimePolicy::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('time_policy', $course?->time_policy?->value ?? 'work_time_required') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="instruction_suitability" :label="__('learning.field.instruction_suitability')" required :help="__('learning.help.instruction_suitability')">
            @foreach (\App\Enums\Learning\LearningInstructionSuitability::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('instruction_suitability', $course?->instruction_suitability?->value ?? 'supplementary') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="points" type="number" min="0" max="10000" :label="__('learning.field.points')" :value="old('points', $course?->points ?? 0)" />
        <x-input-field name="access_days" type="number" min="1" max="3650" :label="__('learning.field.access_days')" :value="old('access_days', $course?->access_days)" />
        <x-checkbox-field name="certificate_enabled" :label="__('learning.field.certificate')" :checked="(bool) old('certificate_enabled', $course?->certificate_enabled)" />
        <x-checkbox-field name="sequential" :label="__('learning.field.sequential')" :checked="(bool) old('sequential', $course?->sequential)" />
    </x-form-group>
</x-modal>
