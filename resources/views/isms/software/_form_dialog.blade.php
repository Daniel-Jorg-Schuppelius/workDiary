{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Softwareprodukt (in #entry-modal geladen).
  Variablen: $product (IsmsSoftwareProduct|null), $owners (Collection id/name)
--}}
@php
    $isEdit = $product !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_software') : __('isms.action.create_software')"
    :eyebrow="__('isms.title.software')"
    icon="apps"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.software.update', $product) : route('isms.software.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_software')">

    <x-form-group :legend="__('isms.group.software')" icon="apps" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.name') }} *</span>
            <input type="text" name="name" required minlength="2" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('name', $product?->name) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.vendor') }}</span>
            <input type="text" name="vendor" maxlength="120"
                   class="input input-bordered w-full"
                   value="{{ old('vendor', $product?->vendor) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.product_version') }}</span>
            <input type="text" name="product_version" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('product_version', $product?->product_version) }}"
                   placeholder="{{ __('isms.hint.product_version') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.category') }}</span>
            <select name="category" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach (\App\Enums\Isms\SoftwareCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $product?->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.notes') }}</span>
            <textarea name="notes" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('notes', $product?->notes) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.support')" icon="support_agent" tone="warning" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.support_status') }} *</span>
            <select name="support_status" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\SupportStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('support_status', $product?->support_status?->value ?? 'unknown') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.eol_on') }}</span>
            <input type="date" name="eol_on"
                   class="input input-bordered w-full"
                   value="{{ old('eol_on', $product?->eol_on?->toDateString()) }}">
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.eol_on') }}</span>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $product?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
    </x-form-group>
</x-modal>
