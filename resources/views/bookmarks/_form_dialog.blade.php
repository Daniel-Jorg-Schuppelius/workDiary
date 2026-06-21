{{-- Variablen: $bookmark, $isEdit --}}
@php
    /** @var \App\Models\UserBookmark $bookmark */
    /** @var bool $isEdit */
    $isEdit ??= $bookmark->exists;
    $action = $isEdit ? route('bookmarks.update', $bookmark) : route('bookmarks.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $title = $isEdit ? __('Lesezeichen bearbeiten') : __('Neues Lesezeichen');
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Lesezeichen')"
    icon="bookmark"
    tone="primary"
    size="md"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Daten')" icon="bookmark" tone="primary" cols="2">
        <x-input-field name="label" :label="__('Bezeichnung')" required maxlength="120" span="2" :value="old('label', $bookmark->label)" />

        <x-input-field name="url" :label="__('URL')" required maxlength="2048" span="2" :value="old('url', $bookmark->url)" />

        <x-input-field name="icon" :label="__('Icon')" maxlength="32" class="font-mono" placeholder="bookmark" :value="old('icon', $bookmark->icon)" />

        <x-input-field name="sort_order" type="number" :label="__('Reihenfolge')" min="0" max="9999" :value="old('sort_order', $bookmark->sort_order ?? 0)" />
    </x-form-group>
</x-modal>
