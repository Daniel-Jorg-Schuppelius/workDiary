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
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="bm-label">{{ __('Bezeichnung') }} *</label>
            <input id="bm-label" type="text" name="label" required maxlength="120"
                   class="input input-bordered w-full @error('label') input-error @enderror"
                   value="{{ old('label', $bookmark->label) }}">
            @error('label')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="bm-url">{{ __('URL') }} *</label>
            <input id="bm-url" type="text" name="url" required maxlength="2048"
                   class="input input-bordered w-full @error('url') input-error @enderror"
                   value="{{ old('url', $bookmark->url) }}">
            @error('url')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="bm-icon">{{ __('Icon') }}</label>
            <input id="bm-icon" type="text" name="icon" maxlength="32"
                   class="input input-bordered w-full font-mono"
                   placeholder="bookmark"
                   value="{{ old('icon', $bookmark->icon) }}">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="bm-sort">{{ __('Reihenfolge') }}</label>
            <input id="bm-sort" type="number" min="0" max="9999" name="sort_order"
                   class="input input-bordered w-full"
                   value="{{ old('sort_order', $bookmark->sort_order ?? 0) }}">
        </div>
    </x-form-group>
</x-modal>
