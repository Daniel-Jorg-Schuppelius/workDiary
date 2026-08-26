{{--
  Created on   : Thu May 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Rolle anlegen oder bearbeiten (Permission-Matrix). --}}
@php
    /** @var \Spatie\Permission\Models\Role $role */
    /** @var array<int, \App\Enums\User\Permission[]> $permissions */
    /** @var list<string> $assigned */
    $isEdit = (bool) ($role->id ?? false);
@endphp
<x-modal
    :title="$isEdit ? __('access.title.role_edit', ['name' => $role->name]) : __('access.title.role_new')"
    icon="shield_person"
    tone="primary"
    size="xl"
    :action="$isEdit ? route('admin.access.roles.update', $role) : route('admin.access.roles.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('access.action.save') : __('access.action.create')"
>
    @unless ($isEdit)
        <x-form-group :label="__('access.field.role_name')" name="name" required>
            <input type="text"
                   name="name"
                   value="{{ old('name') }}"
                   class="input input-bordered w-full font-mono"
                   pattern="[a-z0-9._\-]+"
                   maxlength="80"
                   required />
            <p class="text-xs text-muted mt-1">{{ __('access.help.role_name') }}</p>
        </x-form-group>
    @endunless

    <div class="mt-3">
        <p class="text-sm text-muted mb-2">{{ __('access.help.role_permissions') }}</p>
        @include('admin.access._permission_matrix', [
            'grouped' => $permissions,
            'assigned' => $assigned,
            'name' => 'permissions[]',
        ])
    </div>
</x-modal>
