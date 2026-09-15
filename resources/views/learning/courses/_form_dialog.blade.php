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
        <x-select-field name="category_id" :label="__('learning.field.category')" :hint="__('learning.help.course_category')">
            <option value="">{{ __('learning.field.no_category') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->sqid }}" @selected((string) old('category_id', $course?->category?->sqid) === (string) $category->sqid)>{{ $category->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    {{-- Verfügbarkeit (MVP-788): Fenster und Grenze gelten für Katalog und
         Selbsteinschreibung — eine Zuweisung durch die Verwaltung bleibt frei. --}}
    <x-form-group :legend="__('learning.field.availability')" icon="event_available" tone="success" cols="3">
        <x-input-field name="available_from" type="date" :label="__('learning.field.available_from')" :value="old('available_from', $course?->available_from?->format('Y-m-d'))" />
        <x-input-field name="available_until" type="date" :label="__('learning.field.available_until')" :value="old('available_until', $course?->available_until?->format('Y-m-d'))" />
        <x-input-field name="max_enrollments" type="number" min="1" max="100000" :label="__('learning.field.max_enrollments')" :hint="__('learning.help.max_enrollments')" :value="old('max_enrollments', $course?->max_enrollments)" />
    </x-form-group>

    {{-- Prüfung ohne Kurs und Voraussetzungen (MVP-784): die Art ist nur beim
         Anlegen wählbar; Voraussetzungen sperren den Start, nie die Zuweisung. --}}
    <x-form-group :legend="__('learning.field.kind_and_prerequisites')" icon="rule" tone="neutral" cols="2">
        @unless ($isEdit)
            <x-select-field name="kind" :label="__('learning.field.kind')" :hint="__('learning.help.exam_kind')">
                @foreach (\App\Enums\Learning\LearningCourseKind::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('kind', 'course') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-select-field>
        @else
            <x-input-field name="kind_label" :label="__('learning.field.kind')" :value="$course->kind->label()" disabled />
        @endunless
        <x-select-field name="exam_for_course_id" :label="__('learning.field.exam_target')" :hint="__('learning.help.exam_target')">
            <option value="">{{ __('learning.field.no_exam_target') }}</option>
            @foreach ($courseOptions as $option)
                <option value="{{ $option->sqid }}" @selected((string) old('exam_for_course_id', $course?->examTarget?->sqid) === (string) $option->sqid)>{{ $option->title }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="prerequisite_mode" :label="__('learning.field.prerequisite_mode')">
            <option value="all" @selected(old('prerequisite_mode', $course?->prerequisite_mode ?? 'all') === 'all')>{{ __('learning.field.prerequisite_mode_all') }}</option>
            <option value="any" @selected(old('prerequisite_mode', $course?->prerequisite_mode ?? 'all') === 'any')>{{ __('learning.field.prerequisite_mode_any') }}</option>
        </x-select-field>
        @php
            $selectedPrerequisites = collect(old('prerequisite_course_ids', $course?->prerequisites?->map(fn ($c) => $c->sqid)->all() ?? []))->map(fn ($v) => (string) $v)->all();
        @endphp
        <div class="sm:col-span-2">
            <span class="label-text">{{ __('learning.field.prerequisites') }}</span>
            <p class="mb-1 text-xs text-muted">{{ __('learning.help.prerequisites') }}</p>
            <div class="grid gap-1 sm:grid-cols-2">
                @foreach ($courseOptions as $option)
                    <x-checkbox-field name="prerequisite_course_ids[]" :id="'prereq-' . $option->sqid" :value="$option->sqid"
                                      :label="$option->title" :toggle="false" :with-hidden="false"
                                      :checked="in_array((string) $option->sqid, $selectedPrerequisites, true)" />
                @endforeach
            </div>
        </div>
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
        <x-checkbox-field name="lti_available" :label="__('learning.field.lti_available')" :checked="(bool) old('lti_available', $course?->lti_available)" />
    </x-form-group>
</x-modal>
