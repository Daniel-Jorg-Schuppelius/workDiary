{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        <x-input-field name="name" :label="__('Name')" required maxlength="60" :value="old('name', $tag?->name)" />
    </x-form-group>
</x-modal>
