{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Produkt anlegen/bearbeiten (MVP-370, produktmodell-konzept.md) --}}
@php
    /** @var \App\Models\Product $product */
    $isEdit = $product->exists;
@endphp
<x-modal
    :title="$isEdit ? __('products.title.edit') : __('products.title.create')"
    icon="category"
    tone="primary"
    :action="$isEdit ? route('products.update', $product) : route('products.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('products.action.save') : __('products.action.create')"
>
    <x-form-group :legend="__('products.field.basics')" icon="category" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="prod-manufacturer">{{ __('products.field.manufacturer') }}</label>
            <input id="prod-manufacturer" type="text" name="manufacturer" required maxlength="190"
                   value="{{ old('manufacturer', $product->manufacturer) }}"
                   class="input input-bordered w-full">
            @error('manufacturer')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="prod-model">{{ __('products.field.model') }}</label>
            <input id="prod-model" type="text" name="model" required maxlength="190"
                   value="{{ old('model', $product->model) }}"
                   class="input input-bordered w-full font-mono">
            @error('model')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="prod-name">{{ __('products.field.name') }}</label>
            <input id="prod-name" type="text" name="name" maxlength="190"
                   value="{{ old('name', $product->name) }}"
                   class="input input-bordered w-full" placeholder="{{ __('products.field.name_placeholder') }}">
            <p class="text-xs text-base-content/60">{{ __('products.field.name_help') }}</p>
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="prod-group">{{ __('products.field.product_group') }}</label>
            <select id="prod-group" name="product_group_classification_id" class="select select-bordered w-full">
                <option value="">{{ __('products.field.no_group') }}</option>
                @foreach ($productGroups as $group)
                    <option value="{{ \App\Support\Sqid::encode(\App\Models\Classification::class, $group->id) }}"
                            @selected(old('product_group_classification_id', $product->product_group_classification_id ? \App\Support\Sqid::encode(\App\Models\Classification::class, $product->product_group_classification_id) : '') === \App\Support\Sqid::encode(\App\Models\Classification::class, $group->id))>{{ $group->label }}</option>
                @endforeach
            </select>
            @error('product_group_classification_id')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="prod-status">{{ __('products.field.status') }}</label>
            <select id="prod-status" name="status" class="select select-bordered w-full" required>
                @foreach (\App\Enums\Product\ProductStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $product->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="prod-notes">{{ __('products.field.notes') }}</label>
            <textarea id="prod-notes" name="notes" rows="3" maxlength="5000"
                      class="textarea textarea-bordered w-full">{{ old('notes', $product->notes) }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('products.destroy', $product)"
                  method="DELETE"
                  :confirm="__('products.action.delete_confirm')"
                  :confirm-label="__('products.action.delete')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('products.action.delete') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
