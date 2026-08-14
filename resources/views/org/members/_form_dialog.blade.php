{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $member (User|null), $isEdit, $roles --}}
@php
    $isEdit = $isEdit ?? false;
    $action = $isEdit
        ? route('org.members.update', $member)
        : route('org.members.store');
@endphp

<x-modal
    :title="$isEdit ? __('Mitarbeiter bearbeiten') : __('Mitarbeiter anlegen')"
    :eyebrow="__('Mitarbeiterverwaltung')"
    icon="group"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    @include('org.members._form', [
        'member' => $member ?? null,
        'roles' => $roles,
        'canManageMembers' => $canManageMembers ?? true,
        'canManagePayroll' => $canManagePayroll ?? false,
    ])

    <x-validation-errors />
</x-modal>
