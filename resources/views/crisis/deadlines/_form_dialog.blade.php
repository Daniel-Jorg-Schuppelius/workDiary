{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Meldefrist der Organisation (MVP-1589). Erwartet: $template (?CrisisDeadlineTemplate), $category (?string) --}}
<x-modal
    :title="$template ? __('crisis.deadlines.edit') : __('crisis.deadlines.create')"
    :eyebrow="__('crisis.deadlines.title')"
    icon="timer"
    tone="primary"
    :action="$template ? route('crisis.deadlines.update', $template) : route('crisis.deadlines.store')"
    :method="$template ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('crisis.deadlines.save')"
>
    <x-form-group :legend="__('crisis.deadlines.title')" icon="timer" tone="primary" cols="2" :description="__('crisis.deadlines.form_hint')">
        <x-select-field name="category" :label="__('crisis.deadlines.field.category')" required>
            @foreach (\App\Models\Crisis\CrisisCase::CATEGORIES as $option)
                <option value="{{ $option }}" @selected(old('category', $category) === $option)>{{ __('values.' . $option) }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="offset_hours" type="number" min="0" max="87600" :label="__('crisis.deadlines.field.offset_hours')"
                       :hint="__('crisis.deadlines.field.offset_hours_hint')" :value="old('offset_hours', $template?->offset_hours)" />
        <x-input-field name="label" :label="__('crisis.deadlines.field.label')" required maxlength="200" span="2" :value="old('label', $template?->label)" />
        <x-input-field name="source" :label="__('crisis.deadlines.field.source')" maxlength="300" span="2" :value="old('source', $template?->source)" />
        <x-checkbox-field name="active" :label="__('crisis.deadlines.field.active')" :checked="(bool) old('active', $template?->active ?? true)" />
    </x-form-group>
</x-modal>
