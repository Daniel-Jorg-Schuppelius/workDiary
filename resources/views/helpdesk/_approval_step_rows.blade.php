{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _approval_step_rows.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeilen des Genehmigungsschritt-Editors (Change, Katalogeintrag): steht
  innerhalb des Alpine-Bausteins mit `items`, `fieldName()` und `remove()`;
  erwartet $orgUsers und die Rollenliste der einbindenden View.
--}}
<template x-for="(it, i) in items" :key="i">
    <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-6 items-end">
            <div class="fieldset md:col-span-2">
                <label :for="fieldName(i, 'type')" class="fieldset-label">{{ __('Schritt-Typ') }}</label>
                <select :id="fieldName(i, 'type')" :name="fieldName(i, 'type')" x-model="it.type"
                        class="select select-sm select-bordered w-full">
                    <option value="role">{{ __('Rolle') }}</option>
                    <option value="user">{{ __('Benutzer') }}</option>
                </select>
            </div>
            <div class="fieldset md:col-span-3" x-show="it.type === 'user'">
                <label :for="fieldName(i, 'user')" class="fieldset-label">{{ __('Genehmiger (Benutzer)') }}</label>
                <select :id="fieldName(i, 'user')" :name="fieldName(i, 'user')" x-model="it.user"
                        class="select select-sm select-bordered w-full">
                    <option value="">—</option>
                    @foreach ($orgUsers as $orgUser)
                        <option value="{{ $orgUser->sqid }}">{{ $orgUser->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fieldset md:col-span-3" x-show="it.type === 'role'">
                <label :for="fieldName(i, 'role')" class="fieldset-label">{{ __('Genehmiger (Rolle)') }}</label>
                <select :id="fieldName(i, 'role')" :name="fieldName(i, 'role')" x-model="it.role"
                        class="select select-sm select-bordered w-full">
                    <option value="">—</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-end">
                <x-icon-btn icon="close" tone="error" type="button"
                            :label="__('Schritt entfernen')" @click="remove(i)" />
            </div>
        </div>
    </div>
</template>
