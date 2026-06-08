{{-- Variablen: $category, $isEdit --}}
@php
    /** @var \App\Models\EventCategory|null $category */
    /** @var bool $isEdit */
    $isEdit ??= false;
    $action  = $isEdit ? route('event-categories.update', $category) : route('event-categories.store');
    $method  = $isEdit ? 'PUT' : 'POST';
    $title   = $isEdit ? __('Kategorie bearbeiten') : __('Neue Kategorie');
    $dialogUrl = ($isEdit ? route('event-categories.edit', $category) : route('event-categories.create')).'?dialog=1';
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Veranstaltungs-Kategorien')"
    icon="category"
    tone="primary"
    size="lg"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$category?->is_active ?? true"
            :color="$category?->color ?? '#3b82f6'" />
    </x-slot:headerActions>

    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">

    <x-form-group :legend="__('Stammdaten')" icon="category" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="cat-name">{{ __('Name') }} *</label>
            <input id="cat-name" type="text" name="name" required
                   class="input input-bordered w-full @error('name') input-error @enderror"
                   value="{{ old('name', $category?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="cat-desc">{{ __('Beschreibung') }}</label>
            <textarea id="cat-desc" name="description" rows="2"
                      class="textarea textarea-bordered w-full">{{ old('description', $category?->description) }}</textarea>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Zertifikat')" icon="workspace_premium" tone="warning" cols="1">
        <x-slot:legendActions>
            <label class="label cursor-pointer justify-start gap-2 py-0">
                <input type="hidden" name="requires_certificate" value="0">
                <input type="checkbox" name="requires_certificate" value="1"
                       class="toggle toggle-sm toggle-warning"
                       @checked(old('requires_certificate', $category?->requires_certificate))>
                <span class="label-text text-xs">{{ __('Zertifikat erforderlich') }}</span>
            </label>
        </x-slot:legendActions>

        <div class="fieldset">
            <label class="fieldset-label" for="cat-cert-months">{{ __('Gültigkeit (Monate)') }}</label>
            <input id="cat-cert-months" type="number" min="1" max="120"
                   name="certificate_valid_months"
                   class="input input-bordered w-full"
                   value="{{ old('certificate_valid_months', $category?->certificate_valid_months) }}">
        </div>
    </x-form-group>

    <x-form-group :legend="__('Reminder')" icon="notifications" tone="info">
        <p class="text-xs opacity-70 mb-2">
            {{ __('Minuten vor Beginn — pro Eintrag eine Zeile (z. B. 10080 = 1 Woche, 1440 = 1 Tag, 60 = 1 Stunde).') }}
        </p>
        @php($reminderOffsetItems = old('reminder_offsets', $category?->reminder_offsets ?? [10080, 1440, 60]))
        <div x-data="reminderOffsets" data-items="{{ json_encode($reminderOffsetItems) }}" class="space-y-2">
            <template x-for="(it, i) in items" :key="i">
                <div class="flex items-center gap-2">
                    <input type="number" min="0"
                           :name="fieldName(i)"
                           x-model.number="it.value"
                           class="input input-sm input-bordered w-32 font-mono">
                    <span class="opacity-70 text-xs">{{ __('Minuten') }}</span>
                    <x-icon-btn icon="close" tone="error" type="button" :label="__('Entfernen')" @click="remove(i)" />
                </div>
            </template>
            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('Reminder hinzufügen') }}
            </x-icon-btn>
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
