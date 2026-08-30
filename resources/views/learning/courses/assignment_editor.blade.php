{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : assignment_editor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Aufgaben-Editor (Feature 149, MVP-739). Die Rubrik wird als Zeilenliste
  gepflegt: `Schlüssel | Bezeichnung | Punkte` — erklärbar ohne JavaScript.
--}}
@extends('layouts.app')
@section('title', __('learning.field.assignment'))
@section('nav-title', __('learning.field.assignment'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$unit->title" :badge="$course->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.courses.show', $course)"
                            show-label>{{ __('learning.action.back_to_course') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <form method="POST" action="{{ route('learning.courses.units.assignment.update', [$course, $unit]) }}">
            @csrf
            @method('PUT')
            <x-form-group :legend="__('learning.field.assignment')" icon="assignment" tone="primary" cols="2">
                <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" span="2" :value="old('title', $assignment?->title ?? $unit->title)" />
                <x-textarea-field name="instructions" :label="__('learning.field.instructions')" rows="4" span="2" maxlength="20000" :value="old('instructions', $assignment?->instructions)" />
                <x-select-field name="submission_kind" :label="__('learning.field.submission_kind')" required>
                    @foreach (['text', 'file', 'both'] as $kind)
                        <option value="{{ $kind }}" @selected(old('submission_kind', $assignment?->submission_kind ?? 'text') === $kind)>{{ __('learning.field.submission_kind_' . $kind) }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="due_days" type="number" min="1" max="3650" :label="__('learning.field.due_days')" :value="old('due_days', $assignment?->due_days)" />
                <x-input-field name="points" type="number" min="1" max="1000" :label="__('learning.field.score')" required :value="old('points', $assignment?->points ?? 10)" />
                <x-input-field name="pass_percent" type="number" min="1" max="100" :label="__('learning.field.pass_percent')" required :value="old('pass_percent', $assignment?->pass_percent ?? 50)" />
                <x-textarea-field name="rubric" :label="__('learning.field.rubric')" rows="4" span="2" maxlength="5000"
                                  :hint="__('learning.help.rubric_lines')"
                                  :value="old('rubric', collect($assignment?->criteria() ?? [])->map(fn ($c) => ($c['key'] ?? '') . ' | ' . ($c['label'] ?? '') . ' | ' . ($c['max_points'] ?? 0))->implode(PHP_EOL))" />
                <x-checkbox-field name="requires_second_opinion" :label="__('learning.field.requires_second_opinion')" span="2"
                                  :checked="(bool) old('requires_second_opinion', $assignment?->requires_second_opinion)" />
            </x-form-group>
            <div class="mt-3 flex justify-end">
                <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.save') }}</x-icon-btn>
            </div>
        </form>
        <p class="mt-3 text-xs text-muted">{{ __('learning.help.rubric_frozen') }}</p>
    </x-card>
</x-page-shell>
@endsection
