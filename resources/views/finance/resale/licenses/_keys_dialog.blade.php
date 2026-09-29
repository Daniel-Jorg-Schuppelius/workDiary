{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _keys_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schlüssel einer freien Lizenz pflegen (MVP-1024). Vorhandene Werte werden
  nicht angezeigt: leeres Feld = unverändert, Ändern oder Entfernen braucht
  einen Grund.
--}}
<x-modal
    :title="__('resale.license.action.edit_keys')"
    :eyebrow="$unit->label()"
    icon="edit_note" tone="primary"
    :action="route('finance.resale.licenses.units.keys.update', $unit)"
    method="PUT"
    autocomplete="off"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.license.action.save')">
    <x-form-group :legend="__('resale.license.field.key_roles')" icon="key" tone="primary" cols="1" :description="__('resale.license.hint.keys_edit')">
        @foreach ($unit->batch->keyRoles() as $role)
            @php($has = in_array($role['code'], $present, true))
            <div class="flex flex-col gap-1">
                <x-input-field :name="'license_keys[' . $role['code'] . ']'" :id="'license-key-' . $role['code']" :error="'license_keys.' . $role['code']"
                               :label="$role['label']" value="" autocomplete="off" spellcheck="false"
                               :hint="$has ? __('resale.license.hint.key_present') : __('resale.license.hint.key_missing')" />
                @if ($has)
                    <x-checkbox-field name="remove[]" :id="'license-key-remove-' . $role['code']" :value="$role['code']" :toggle="false" :label="__('resale.license.field.remove_key', ['role' => $role['label']])" />
                @endif
            </div>
        @endforeach
    </x-form-group>
    <x-input-field name="reason" :label="__('resale.license.field.reason')" :value="old('reason')" maxlength="255" :hint="__('resale.license.hint.keys_reason')" />
</x-modal>
