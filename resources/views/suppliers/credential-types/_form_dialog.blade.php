{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eigener Nachweistyp (MVP-1586). Erwartet: $type (?SupplierCredentialType), $template (?Katalogzeile als Vorlage), $tags --}}
@php
    $source = $type ?? $template;
    $selectedTags = array_map('intval', (array) old('required_tag_ids', $type?->required_tag_ids ?? []));
    $active = (string) old('is_active', ($type?->is_active ?? true) ? '1' : '0');
    $required = (string) old('is_required_default', ($source?->is_required_default ?? true) ? '1' : '0');
@endphp
<x-modal
    :title="$type ? __('procurement.credential_types.action.edit') : __('procurement.credential_types.action.create')"
    :eyebrow="__('procurement.credential_types.title')"
    icon="verified_user"
    tone="primary"
    :action="$type ? route('suppliers.credential-types.update', $type) : route('suppliers.credential-types.store')"
    :method="$type ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <input type="hidden" name="_dialog_url" value="{{ ($type ? route('suppliers.credential-types.edit', $type) : route('suppliers.credential-types.create')) }}?dialog=1">

    <x-form-group :legend="__('procurement.credential_types.title')" icon="verified_user" tone="primary" cols="2">
        <x-input-field name="name" id="credential-type-name" :label="__('procurement.credential_types.field.name')" required maxlength="191" span="2"
                       :value="old('name', $source?->name)" />
        <x-input-field name="description" id="credential-type-description" :label="__('procurement.credential_types.field.description')" maxlength="500" span="2"
                       :value="old('description', $source?->description)" />
        <x-input-field name="default_validity_months" id="credential-type-validity" type="number" min="1" max="240" step="1" inputmode="numeric"
                       :label="__('procurement.credential_types.field.validity')" :hint="__('procurement.credential_types.field.validity_hint')"
                       :value="old('default_validity_months', $source?->default_validity_months)" />
        <x-input-field name="warn_days_before" id="credential-type-warn" type="number" min="0" max="365" step="1" required inputmode="numeric"
                       :label="__('procurement.credential_types.field.warn_days_before')"
                       :value="old('warn_days_before', $source?->warn_days_before ?? 30)" />
        <x-select-field name="blocking_mode" id="credential-type-mode" :label="__('procurement.credential_types.field.blocking_mode')" required
                        :hint="__('procurement.credential_types.field.blocking_mode_hint')">
            @foreach ([\App\Models\Supplier\SupplierCredentialType::MODE_WARN, \App\Models\Supplier\SupplierCredentialType::MODE_BLOCK] as $mode)
                <option value="{{ $mode }}" @selected(old('blocking_mode', $source?->blocking_mode ?? 'warn') === $mode)>{{ __('procurement.credential_types.mode.' . $mode) }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="is_active" id="credential-type-active" :label="__('procurement.credential_types.field.state')" required
                        :hint="__('procurement.credential_types.field.state_hint')">
            <option value="1" @selected($active === '1')>{{ __('procurement.credential_types.state.active') }}</option>
            <option value="0" @selected($active === '0')>{{ __('procurement.credential_types.state.inactive') }}</option>
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('procurement.credential_types.field.required')" icon="rule" tone="primary" cols="1"
                  :description="__('procurement.credential_types.required.description')">
        <x-select-field name="is_required_default" id="credential-type-required" :label="__('procurement.credential_types.field.required')" required>
            <option value="1" @selected($required === '1')>{{ __('procurement.credential_types.required.yes') }}</option>
            <option value="0" @selected($required === '0')>{{ __('procurement.credential_types.required.optional') }}</option>
        </x-select-field>
        @if ($tags->isNotEmpty())
            <fieldset class="fieldset">
                <legend class="fieldset-label">{{ __('procurement.credential_types.field.required_tags') }}</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($tags as $tag)
                        <label class="label cursor-pointer justify-start gap-2">
                            <input type="checkbox" name="required_tag_ids[]" value="{{ $tag->sqid }}" class="checkbox checkbox-sm" @checked(in_array($tag->id, $selectedTags, true))>
                            <span class="label-text">{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-muted">{{ __('procurement.credential_types.field.required_tags_hint') }}</p>
            </fieldset>
        @endif
    </x-form-group>
</x-modal>
