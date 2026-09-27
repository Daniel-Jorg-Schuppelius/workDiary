{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Profilvariante anlegen (MVP-933). Erwartet: $bases (Code → Bezeichnung) --}}
<x-modal
    :title="__('branch_profile.variant.create')"
    :eyebrow="__('Branchenprofile')"
    icon="tune"
    tone="primary"
    :action="route('admin.branch-profile-variants.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('branch_profile.variant.create')"
>
    <x-form-group :legend="__('branch_profile.variant.title')" icon="tune" tone="primary" cols="2">
        <x-select-field name="base_code" :label="__('branch_profile.variant.field.base_code')" required span="2">
            @foreach ($bases as $code => $label)
                <option value="{{ $code }}" @selected(old('base_code') === $code)>{{ $label }} ({{ $code }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="code" :label="__('branch_profile.variant.field.code')" :value="old('code')" :hint="__('branch_profile.variant.hint.code')" required />
        <x-input-field name="label" :label="__('branch_profile.variant.field.label')" :value="old('label')" required />
        <x-textarea-field name="description" :label="__('branch_profile.variant.field.description')" rows="2" span="2">{{ old('description') }}</x-textarea-field>
    </x-form-group>
</x-modal>
