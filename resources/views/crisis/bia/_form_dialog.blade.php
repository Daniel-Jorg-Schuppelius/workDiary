{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Geschäftsprozess im BIA-Register (MVP-943). Erwartet: $process (?CrisisBusinessProcess), $users --}}
<x-modal
    :title="$process ? __('crisis.bia.edit') : __('crisis.bia.create')"
    :eyebrow="__('crisis.bia.title')"
    icon="account_tree"
    tone="primary"
    :action="$process ? route('crisis.bia.update', $process) : route('crisis.bia.store')"
    :method="$process ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('crisis.bia.save')"
>
    <x-form-group :legend="__('crisis.bia.title')" icon="account_tree" tone="primary" cols="3">
        <x-input-field name="name" :label="__('crisis.bia.field.name')" :value="old('name', $process?->name)" required span="2" />
        <x-select-field name="criticality" :label="__('crisis.bia.field.criticality')" required>
            @foreach (\App\Enums\Crisis\CrisisProcessCriticality::cases() as $level)
                <option value="{{ $level->value }}" @selected(old('criticality', $process?->criticality->value ?? 'medium') === $level->value)>{{ $level->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="rto_hours" type="number" min="0" :label="__('crisis.bia.field.rto_hours')" :value="old('rto_hours', $process?->rto_hours)" />
        <x-input-field name="rpo_hours" type="number" min="0" :label="__('crisis.bia.field.rpo_hours')" :value="old('rpo_hours', $process?->rpo_hours)" />
        <x-input-field name="mtpd_hours" type="number" min="0" :label="__('crisis.bia.field.mtpd_hours')" :value="old('mtpd_hours', $process?->mtpd_hours)" />
        <x-select-field name="owner_user_id" :label="__('crisis.bia.field.owner')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected($process?->owner_user_id === $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="review_due_on" type="date" :label="__('crisis.bia.field.review_due_on')" :value="old('review_due_on', $process?->review_due_on?->toDateString())" />
        <x-checkbox-field name="is_active" :label="__('crisis.bia.field.is_active')" :checked="(bool) old('is_active', $process?->is_active ?? true)" />
        <x-textarea-field name="dependencies" :label="__('crisis.bia.field.dependencies')" rows="2" span="3">{{ old('dependencies', $process?->dependencies) }}</x-textarea-field>
        <x-textarea-field name="description" :label="__('crisis.bia.field.description')" rows="2" span="3">{{ old('description', $process?->description) }}</x-textarea-field>
    </x-form-group>
</x-modal>
