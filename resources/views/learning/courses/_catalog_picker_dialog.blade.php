{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _catalog_picker_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Katalogfragen in eine Prüfung übernehmen (Feature 149, MVP-782). Zeigt, was
  noch nicht in der Prüfung steht. Variablen: $course, $unit, $questions,
  $categories, $category, $kind, $search.
--}}
<x-modal
    :title="__('learning.action.from_catalog')"
    :eyebrow="$unit->title"
    icon="library_add"
    tone="primary"
    size="wide"
    :action="route('learning.courses.units.quiz.attach', [$course, $unit])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.attach_selected')">

    @if ($questions->isEmpty())
        <x-empty-state icon="quiz" :title="__('learning.empty.catalog_picker')" compact />
    @else
        <p class="mb-2 text-xs text-muted">{{ __('learning.help.catalog_picker') }}</p>
        <ul class="max-h-96 space-y-1 overflow-y-auto">
            @foreach ($questions as $question)
                <li class="rounded-box border border-base-300 px-3 py-2">
                    <label class="flex cursor-pointer items-start gap-2 text-sm">
                        <input type="checkbox" name="question_ids[]" value="{{ $question->sqid }}" class="checkbox checkbox-sm mt-0.5">
                        <span>
                            <span class="font-medium">{{ $question->title ?? \Illuminate\Support\Str::limit($question->prompt, 90) }}</span>
                            <span class="block text-xs text-muted">
                                {{ $question->kind->label() }} · {{ $question->points }} {{ __('learning.field.score') }}
                                @if ($question->category)
                                    · {{ $question->category->name }}
                                @endif
                            </span>
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
    @endif
</x-modal>
