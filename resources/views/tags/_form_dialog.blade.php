{{-- Erwartet: $tag, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $tag ? route('tags.update', $tag) : route('tags.store');
    $dialogUrl = ($tag ? route('tags.edit', $tag) : route('tags.create')) . '?dialog=1';
@endphp

<x-modal
    :title="$tag ? __('Tag bearbeiten') : __('Neuer Tag')"
    :eyebrow="__('Schlagwort')"
    icon="label"
    tone="success"
    :action="$action"
    :method="$tag ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$tag ? __('Speichern') : __('Anlegen')">

    <x-slot:headerActions>
        <x-dialog-status-controls
            :name="null"
            :color="$tag?->color ?? '#3b82f6'" />
    </x-slot:headerActions>

    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Tag-Daten')" icon="label" tone="success">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Name') }}</label>
            <input name="name" type="text" required maxlength="60"
                   class="input input-bordered w-full"
                   value="{{ old('name', $tag?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
