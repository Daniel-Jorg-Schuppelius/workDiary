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
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.scope') }} *</span>
            <select name="scope" required class="select select-bordered w-full">
                @foreach ($scopes as $scopeOption)
                    <option value="{{ $scopeOption->sqid }}" @selected(old('scope', $scope?->sqid) === $scopeOption->sqid)>{{ $scopeOption->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.norm') }} *</span>
            <input type="text" name="norm" required maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('norm') }}"
                   placeholder="{{ __('isms.hint.norm') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.edition') }}</span>
            <input type="text" name="edition" maxlength="16"
                   class="input input-bordered w-full"
                   value="{{ old('edition') }}"
                   placeholder="{{ __('isms.hint.edition') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.notes') }}</span>
            <textarea name="notes" rows="3" maxlength="5000"
                      class="textarea textarea-bordered w-full">{{ old('notes') }}</textarea>
        </label>
    </x-form-group>
</x-modal>
