{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _manual_grade_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Manuelle Note (Feature 149, MVP-790). Variablen: $course, $enrollment,
  $gradebookComponent (Komponente — nicht $component, das gehört Blade), $history (bisherige Einträge, jüngster zuerst).
--}}
<x-modal
    :title="__('learning.action.record_grade')"
    :eyebrow="$enrollment->learnerName() . ' · ' . $gradebookComponent->title"
    icon="edit_note"
    tone="primary"
    :action="route('learning.courses.gradebook.grade.store', [$course, $enrollment, $gradebookComponent])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.record_grade')">

    <x-form-group :legend="__('learning.field.manual_grade')" icon="grading" tone="primary" cols="2">
        <x-input-field name="points" type="number" min="0" :max="$gradebookComponent->max_points" required
                       :label="__('learning.field.score') . ' (0–' . $gradebookComponent->max_points . ')'"
                       :value="old('points', $history->first()?->points)" />
        <x-textarea-field name="note" :label="__('learning.field.correction')" rows="2" maxlength="2000" span="2" :value="old('note')" />
        <p class="text-xs text-muted sm:col-span-2">{{ __('learning.help.manual_grade') }}</p>
    </x-form-group>

    @if ($history->isNotEmpty())
        <x-form-group :legend="__('learning.field.history')" icon="history" tone="neutral" cols="1">
            <ul class="space-y-1 text-sm">
                @foreach ($history as $grade)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 px-3 py-2">
                        <span>{{ $grade->points }} / {{ $grade->max_points }}@if ($grade->note) — {{ $grade->note }}@endif</span>
                        <span class="text-xs text-muted">{{ $grade->graded_at?->translatedFormat('d.m.Y H:i') }} · {{ $grade->gradedBy?->name ?? '–' }}</span>
                    </li>
                @endforeach
            </ul>
        </x-form-group>
    @endif
</x-modal>
