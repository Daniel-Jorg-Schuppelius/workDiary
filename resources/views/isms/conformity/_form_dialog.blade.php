{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage-Dialog Konformitätsstatus (in #entry-modal geladen): zusätzliche
  Norm/Ausgabe je Geltungsbereich manuell anlegen — Start immer bei
  „nicht bewertet" (Statuskette nur über Statuswechsel).
  Variablen: $scope (IsmsScope|null, vorausgewählt), $scopes (Collection)
--}}

<x-modal
    :title="__('isms.action.create_norm_status')"
    :eyebrow="__('isms.title.conformity')"
    icon="workspace_premium"
    tone="primary"
    :action="route('isms.conformity.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.create_norm_status')">

    <x-form-group :legend="__('isms.group.norm_status')" icon="workspace_premium" tone="primary" cols="2">
        <x-select-field name="scope" :label="__('isms.field.scope')" required span="2">
            @foreach ($scopes as $scopeOption)
                <option value="{{ $scopeOption->sqid }}" @selected(old('scope', $scope?->sqid) === $scopeOption->sqid)>{{ $scopeOption->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="norm" :label="__('isms.field.norm')" required maxlength="64"
                       :value="old('norm')"
                       placeholder="{{ __('isms.hint.norm') }}" />
        <x-input-field name="edition" :label="__('isms.field.edition')" maxlength="16"
                       :value="old('edition')"
                       placeholder="{{ __('isms.hint.edition') }}" />
        <x-textarea-field name="notes" :label="__('isms.field.notes')" rows="3" maxlength="5000"
                          span="2"
                          :value="old('notes')" />
    </x-form-group>
</x-modal>
