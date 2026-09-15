{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _components_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Notenbuch-Komponenten (Feature 149, MVP-790). Variablen: $course,
  $units (Prüfungs-/Aufgabeneinheiten), $components (bestehend).
--}}
@php
    $byUnit = $components->whereNotNull('learning_unit_id')->keyBy('learning_unit_id');
    $manual = $components->filter(fn ($c) => $c->isManual())->values();
    $manualRows = max(3, $manual->count() + 1);
@endphp
<x-modal
    :title="__('learning.action.edit_components')"
    :eyebrow="$course->title"
    icon="tune"
    tone="primary"
    size="lg"
    :action="route('learning.courses.gradebook.components.update', $course)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.save')">

    <x-form-group :legend="__('learning.field.components')" icon="checklist" tone="primary" cols="1">
        <p class="text-xs text-muted">{{ __('learning.help.components') }}</p>
        @forelse ($units as $index => $unit)
            @php
                $kind = $unit->quiz !== null ? 'quiz' : 'assignment';
                $existing = $byUnit->get($unit->id);
            @endphp
            <div class="flex flex-wrap items-end gap-3 rounded-box border border-base-300 p-3">
                <input type="hidden" name="components[{{ $index }}][kind]" value="{{ $kind }}">
                <input type="hidden" name="components[{{ $index }}][unit]" value="{{ $unit->sqid }}">
                <x-checkbox-field :name="'components[' . $index . '][enabled]'" :id="'comp-' . $unit->sqid"
                                  :label="$unit->title . ' (' . $unit->kind->label() . ')'"
                                  :checked="(bool) old('components.' . $index . '.enabled', $existing !== null)" />
                <x-input-field :name="'components[' . $index . '][weight]'" :id="'comp-w-' . $unit->sqid" type="number" min="0" max="100" class="w-28"
                               :label="__('learning.field.weight')" :value="old('components.' . $index . '.weight', $existing?->weight_percent)" />
            </div>
        @empty
            <p class="text-sm text-muted">{{ __('learning.empty.gradable_units') }}</p>
        @endforelse
    </x-form-group>

    <x-form-group :legend="__('learning.field.manual_components')" icon="edit_note" tone="warning" cols="1">
        <p class="text-xs text-muted">{{ __('learning.help.manual_grade') }}</p>
        @for ($i = 0; $i < $manualRows; $i++)
            @php
                $index = $units->count() + $i;
                $existing = $manual->get($i);
            @endphp
            <div class="flex flex-wrap items-end gap-3 rounded-box border border-base-300 p-3">
                <input type="hidden" name="components[{{ $index }}][kind]" value="manual">
                @if ($existing)
                    <input type="hidden" name="components[{{ $index }}][id]" value="{{ $existing->sqid }}">
                @endif
                <x-input-field :name="'components[' . $index . '][title]'" :id="'manual-t-' . $i" maxlength="180" class="w-56"
                               :label="__('learning.field.component')" :value="old('components.' . $index . '.title', $existing?->title)" />
                <x-input-field :name="'components[' . $index . '][max_points]'" :id="'manual-m-' . $i" type="number" min="1" max="10000" class="w-28"
                               :label="__('learning.field.max_points')" :value="old('components.' . $index . '.max_points', $existing?->max_points)" />
                <x-input-field :name="'components[' . $index . '][weight]'" :id="'manual-w-' . $i" type="number" min="0" max="100" class="w-28"
                               :label="__('learning.field.weight')" :value="old('components.' . $index . '.weight', $existing?->weight_percent)" />
            </div>
        @endfor
    </x-form-group>
</x-modal>
