{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Geltungsbereich (in #entry-modal geladen).
  is_default ist bewusst NICHT pflegbar (ScopeService).
  Variablen: $scope (IsmsScope|null)
--}}
@php
    $isEdit = $scope !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_scope') : __('isms.action.create_scope')"
    :eyebrow="__('isms.title.scopes')"
    icon="travel_explore"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.scopes.update', $scope) : route('isms.scopes.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_scope')">

    <x-form-group :legend="__('isms.group.scope')" icon="travel_explore" tone="primary" cols="1">
        <x-input-field name="name" :label="__('isms.field.name')" required minlength="3" maxlength="180" :value="old('name', $scope?->name)" />
        <x-textarea-field name="description" :label="__('isms.field.description')" rows="3" maxlength="10000" :value="old('description', $scope?->description)" placeholder="{{ __('isms.hint.scope_description') }}" />
        @if ($isEdit && $scope->is_default)
            <p class="text-xs text-muted">{{ __('isms.scope.default_hint') }}</p>
        @endif
    </x-form-group>
</x-modal>
