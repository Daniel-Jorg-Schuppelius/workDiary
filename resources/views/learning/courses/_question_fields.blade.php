{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _question_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Felder einer Katalogfrage (MVP-738/779/782) — ohne <form>, damit dieselben
  Felder im Prüfungseditor und im Katalog-Dialog stehen. Variablen:
  $question (null beim Anlegen), $lines (zurückgefüllte Optionen), $categories.
--}}
@php
    $settings = $question?->settings ?? [];
    $hasImage = ($settings['image_attachment_id'] ?? null) !== null;
    $selectedCategory = old('category_id', $question?->category?->sqid);
@endphp
<x-form-group :legend="__('learning.field.question')" icon="help" tone="primary" cols="2">
    <x-select-field name="kind" :label="__('learning.field.question_kind')" required>
        @foreach (\App\Enums\Learning\LearningQuestionKind::cases() as $case)
            <option value="{{ $case->value }}" @selected(old('kind', $question?->kind->value ?? 'single') === $case->value)>{{ $case->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="points" type="number" min="1" max="100" :label="__('learning.field.score')" required :value="old('points', $question?->points ?? 1)" />
    <x-input-field name="title" :label="__('learning.field.question_title')" maxlength="180" :hint="__('learning.help.question_title')" :value="old('title', $question?->title)" />
    <x-select-field name="category_id" :label="__('learning.field.category')">
        <option value="">{{ __('learning.field.no_category') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->sqid }}" @selected((string) $selectedCategory === (string) $category->sqid)>{{ $category->name }}</option>
        @endforeach
    </x-select-field>
    <x-textarea-field name="prompt" :label="__('learning.field.prompt')" rows="2" span="2" required maxlength="2000" :value="old('prompt', $question?->prompt)" />
    <x-textarea-field name="options" :label="__('learning.field.options')" rows="4" span="2" maxlength="5000"
                      :hint="__('learning.help.options_lines')" :value="old('options', $lines ?? '')" />
    <x-textarea-field name="explanation" :label="__('learning.field.explanation')" rows="2" span="2" maxlength="2000" :value="old('explanation', $question?->explanation)" />
    <x-input-field name="hint" :label="__('learning.field.hint')" maxlength="1000" span="2" :hint="__('learning.help.hint')" :value="old('hint', $settings['hint'] ?? null)" />
    <x-input-field name="feedback_correct" :label="__('learning.field.feedback_correct')" maxlength="1000" :value="old('feedback_correct', $settings['feedback_correct'] ?? null)" />
    <x-input-field name="feedback_incorrect" :label="__('learning.field.feedback_incorrect')" maxlength="1000" :value="old('feedback_incorrect', $settings['feedback_incorrect'] ?? null)" />
    <x-checkbox-field name="partial_credit" :label="__('learning.help.partial_credit')" :checked="(bool) old('partial_credit', $settings['partial_credit'] ?? false)" />
    <x-checkbox-field name="case_sensitive" :label="__('learning.field.case_sensitive')" :checked="(bool) old('case_sensitive', $settings['case_sensitive'] ?? false)" />
    {{-- Aufsatz (MVP-793): Text, Datei oder beides — nur für die Art „Aufsatz" wirksam. --}}
    <x-select-field name="submission_kind" :label="__('learning.field.essay_submission_kind')" :hint="__('learning.help.essay_upload')">
        @foreach (['text', 'upload', 'both'] as $submissionKind)
            <option value="{{ $submissionKind }}" @selected(old('submission_kind', $settings['submission_kind'] ?? 'text') === $submissionKind)>{{ __('learning.field.submission_kind_' . $submissionKind) }}</option>
        @endforeach
    </x-select-field>
    <div class="sm:col-span-2">
        {{-- Nur für die Bildmarkierung: ohne Bild gibt es nichts zu markieren. --}}
        <label class="label" for="question-image"><span class="label-text">{{ __('learning.field.question_image') }}</span></label>
        <input type="file" id="question-image" name="image" accept="image/*"
               class="file-input file-input-bordered file-input-sm w-full">
        <p class="mt-1 text-xs text-muted">
            {{ __('learning.help.question_image') }}
            @if ($hasImage)
                {{ __('learning.help.question_image_kept') }}
            @endif
        </p>
    </div>
</x-form-group>
