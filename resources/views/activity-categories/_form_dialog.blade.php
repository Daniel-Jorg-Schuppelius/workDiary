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
        <div class="fieldset">
            <label class="fieldset-label" for="ac-key">{{ __('Schlüssel') }} *</label>
            <input id="ac-key" type="text" name="key" required maxlength="64"
                   pattern="[a-z0-9_\-]+"
                   class="input input-bordered w-full font-mono @error('key') input-error @enderror"
                   placeholder="team_meeting"
                   value="{{ old('key', $category?->key) }}"
                   @if ($isEdit) readonly @endif>
            @error('key')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ac-label">{{ __('Bezeichnung') }} *</label>
            <input id="ac-label" type="text" name="label" required maxlength="120"
                   class="input input-bordered w-full @error('label') input-error @enderror"
                   value="{{ old('label', $category?->label) }}">
            @error('label')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ac-type">{{ __('Typ') }} *</label>
            <select id="ac-type" name="activity_type" required
                    class="select select-bordered w-full @error('activity_type') select-error @enderror">
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('activity_type', $category?->activity_type?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('activity_type')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ac-sort">{{ __('Reihenfolge') }}</label>
            <input id="ac-sort" type="number" min="0" max="999" name="sort_order"
                   class="input input-bordered w-full"
                   value="{{ old('sort_order', $category?->sort_order ?? 100) }}">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="ac-icon">{{ __('Icon') }}</label>
            <input id="ac-icon" type="text" name="icon" maxlength="64"
                   class="input input-bordered w-full font-mono"
                   placeholder="category"
                   value="{{ old('icon', $category?->icon) }}">
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="ac-desc">{{ __('Beschreibung') }}</label>
            <textarea id="ac-desc" name="description" rows="2" maxlength="500"
                      class="textarea textarea-bordered w-full">{{ old('description', $category?->description) }}</textarea>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Verhalten')" icon="tune" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label cursor-pointer justify-start gap-2">
                <input type="hidden" name="counts_as_work" value="0">
                <input type="checkbox" name="counts_as_work" value="1"
                       class="toggle toggle-info"
                       @checked(old('counts_as_work', $category?->counts_as_work ?? true))>
                <span>{{ __('Zählt als Arbeit') }}</span>
            </label>
        </div>

        <div class="fieldset">
            <label class="fieldset-label cursor-pointer justify-start gap-2">
                <input type="hidden" name="billable_default" value="0">
                <input type="checkbox" name="billable_default" value="1"
                       class="toggle toggle-success"
                       @checked(old('billable_default', $category?->billable_default ?? false))>
                <span>{{ __('Standardmäßig abrechenbar') }}</span>
            </label>
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
