{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Strategisches Ziel anlegen/bearbeiten (MVP-942). Erwartet: $objective (?StrategicObjective), $users --}}
@php
    $isEdit = $objective !== null;
    $rows = old('key_results', $isEdit ? $objective->keyResults->map(fn ($kr) => [
        'label' => $kr->label, 'unit' => $kr->unit, 'baseline_value' => $kr->baseline_value, 'target_value' => $kr->target_value, 'current_value' => $kr->current_value,
    ])->all() : []);
    $rows = array_pad(array_values((array) $rows), 5, []);
@endphp
<x-modal
    :title="$isEdit ? __('investment.objective.edit') : __('investment.objective.create')"
    :eyebrow="__('investment.objective.title')"
    icon="flag"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('investments.objectives.update', $objective) : route('investments.objectives.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('investment.objective.save')"
>
    <x-form-group :legend="__('investment.objective.title')" icon="flag" tone="primary" cols="2">
        <x-input-field name="title" :label="__('investment.objective.field.title')" :value="old('title', $objective?->title)" required span="2" />
        <x-select-field name="owner_user_id" :label="__('investment.objective.field.owner')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected($objective?->owner_user_id === $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="is_active" :label="__('investment.objective.field.is_active')" :checked="(bool) old('is_active', $objective?->is_active ?? true)" />
        <div class="md:col-span-2">
            <x-date-range from-name="valid_from" to-name="valid_until" layout="split" :from-label="__('investment.objective.field.valid_from')" :to-label="__('investment.objective.field.valid_until')" :from="old('valid_from', $objective?->valid_from?->toDateString())" :to="old('valid_until', $objective?->valid_until?->toDateString())" />
        </div>
        <x-textarea-field name="description" :label="__('investment.objective.field.description')" rows="2" span="2">{{ old('description', $objective?->description) }}</x-textarea-field>
    </x-form-group>
    <x-form-group :legend="__('investment.objective.field.key_results')" icon="monitoring" tone="primary">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.objective.field.label') }}</th>
                    <th>{{ __('investment.objective.field.unit') }}</th>
                    <th>{{ __('investment.objective.field.baseline_value') }}</th>
                    <th>{{ __('investment.objective.field.target_value') }}</th>
                    <th>{{ __('investment.objective.field.current_value') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $i => $row)
                <tr>
                    <td><input type="text" name="key_results[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" maxlength="200" class="input input-bordered input-xs w-full" aria-label="{{ __('investment.objective.field.label') }} {{ $i + 1 }}"></td>
                    <td><input type="text" name="key_results[{{ $i }}][unit]" value="{{ $row['unit'] ?? '' }}" maxlength="20" class="input input-bordered input-xs w-16" aria-label="{{ __('investment.objective.field.unit') }} {{ $i + 1 }}"></td>
                    <td><input type="number" step="any" name="key_results[{{ $i }}][baseline_value]" value="{{ $row['baseline_value'] ?? '' }}" class="input input-bordered input-xs w-24" aria-label="{{ __('investment.objective.field.baseline_value') }} {{ $i + 1 }}"></td>
                    <td><input type="number" step="any" name="key_results[{{ $i }}][target_value]" value="{{ $row['target_value'] ?? '' }}" class="input input-bordered input-xs w-24" aria-label="{{ __('investment.objective.field.target_value') }} {{ $i + 1 }}"></td>
                    <td><input type="number" step="any" name="key_results[{{ $i }}][current_value]" value="{{ $row['current_value'] ?? '' }}" class="input input-bordered input-xs w-24" aria-label="{{ __('investment.objective.field.current_value') }} {{ $i + 1 }}"></td>
                </tr>
            @endforeach
        </x-table>
    </x-form-group>
</x-modal>
