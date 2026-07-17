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
        <x-input-field name="name" :label="__('Name')" required span="2" :value="old('name', $category?->name)" />

        <x-textarea-field name="description" :label="__('Beschreibung')" rows="2" span="2" :value="old('description', $category?->description)" />
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

        <x-input-field name="certificate_valid_months" type="number" :label="__('Gültigkeit (Monate)')" min="1" max="120" :value="old('certificate_valid_months', $category?->certificate_valid_months)" />
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

    <x-validation-errors />
</x-modal>
