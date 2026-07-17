{{-- Variablen: $category, $isEdit --}}
@php
    /** @var \App\Models\ActivityCategory|null $category */
    /** @var bool $isEdit */
    $isEdit ??= false;
    $action  = $isEdit ? route('activity-categories.update', $category) : route('activity-categories.store');
    $method  = $isEdit ? 'PUT' : 'POST';
    $title   = $isEdit ? __('Tätigkeit bearbeiten') : __('Neue Tätigkeit');
    $dialogUrl = ($isEdit ? route('activity-categories.edit', $category) : route('activity-categories.create')).'?dialog=1';

    $types = \App\Enums\Activity\ActivityCategoryType::options();
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Tätigkeiten')"
    icon="category"
    tone="primary"
    size="lg"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-slot:headerActions>
        <x-dialog-status-controls
            name="active"
            :active="$category?->active ?? true"
            :color="$category?->color ?? '#3b82f6'" />
    </x-slot:headerActions>

    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">

    <x-form-group :legend="__('Stammdaten')" icon="category" tone="primary" cols="2">
        <x-input-field name="key" :label="__('Schlüssel')" required maxlength="64"
                       pattern="[a-z0-9_\-]+" class="font-mono"
                       placeholder="team_meeting"
                       :value="old('key', $category?->key)"
                       @if ($isEdit) readonly @endif />

        <x-input-field name="label" :label="__('Bezeichnung')" required maxlength="120"
                       :value="old('label', $category?->label)" />

        <x-select-field name="activity_type" :label="__('Typ')" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('activity_type', $category?->activity_type?->value) === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>

        <x-input-field type="number" min="0" max="999" name="sort_order" :label="__('Reihenfolge')"
                       :value="old('sort_order', $category?->sort_order ?? 100)" />

        <x-input-field name="icon" :label="__('Icon')" maxlength="64"
                       class="font-mono"
                       placeholder="category"
                       :value="old('icon', $category?->icon)" />

        <x-textarea-field name="description" :label="__('Beschreibung')" rows="2" maxlength="500"
                          span="2" :value="old('description', $category?->description)" />
    </x-form-group>

    <x-form-group :legend="__('Verhalten')" icon="tune" tone="info" cols="2">
        <x-checkbox-field name="counts_as_work" :label="__('Zählt als Arbeit')" tone="info"
                          :checked="old('counts_as_work', $category?->counts_as_work ?? true)" />

        <x-checkbox-field name="billable_default" :label="__('Standardmäßig abrechenbar')" tone="success"
                          :checked="old('billable_default', $category?->billable_default ?? false)" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
