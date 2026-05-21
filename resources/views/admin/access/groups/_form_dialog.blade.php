{{-- Dialog: Benutzergruppe anlegen oder bearbeiten. --}}
@php
    /** @var \App\Models\UserGroup $group */
    /** @var array<int, \App\Enums\User\Permission[]> $permissions */
    /** @var \Illuminate\Database\Eloquent\Collection $roles */
    /** @var list<int> $assignedRoles */
    /** @var list<string> $assignedPermissions */
    $isEdit = (bool) ($group->id ?? false);
@endphp
<x-modal
    :title="$isEdit ? __('access.title.group_edit', ['name' => $group->name]) : __('access.title.group_new')"
    icon="groups"
    tone="secondary"
    size="xl"
    :action="$isEdit ? route('admin.access.groups.update', $group) : route('admin.access.groups.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('access.action.save') : __('access.action.create')"
>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <x-form-group :label="__('access.field.group_name')" name="name" class="md:col-span-2" required>
            <input type="text" name="name" value="{{ old('name', $group->name) }}"
                   class="input input-bordered w-full" maxlength="120" required />
        </x-form-group>

        <x-form-group :label="__('access.field.color')" name="color">
            <input type="color" name="color" value="{{ old('color', $group->color ?? '#6b7280') }}"
                   class="input input-bordered w-full h-10" />
        </x-form-group>

        <x-form-group :label="__('access.field.description')" name="description" class="md:col-span-3">
            <textarea name="description" rows="2" maxlength="500"
                      class="textarea textarea-bordered w-full">{{ old('description', $group->description) }}</textarea>
        </x-form-group>
    </div>

    <section class="card bg-base-200/40 mt-3">
        <div class="card-body space-y-2">
            <h3 class="card-title text-base">{{ __('access.title.assigned_roles') }}</h3>
            <p class="text-xs text-base-content/60">{{ __('access.help.group_roles') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @forelse ($roles as $role)
                    <label class="label cursor-pointer justify-start gap-3 hover:bg-base-100 rounded px-2">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                               class="checkbox checkbox-sm"
                               @checked(in_array($role->id, $assignedRoles, true)) />
                        <span class="font-mono text-sm">{{ $role->name }}</span>
                        @if ($role->getAttribute(config('permission.column_names.team_foreign_key', 'team_id')) === null)
                            <span class="badge badge-xs badge-ghost">{{ __('access.badge.global') }}</span>
                        @endif
                    </label>
                @empty
                    <div class="col-span-full text-sm text-base-content/60">{{ __('access.empty.roles') }}</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="card bg-base-200/40 mt-3">
        <div class="card-body space-y-2">
            <h3 class="card-title text-base">{{ __('access.title.direct_permissions') }}</h3>
            <p class="text-xs text-base-content/60">{{ __('access.help.group_permissions') }}</p>

            @include('admin.access._permission_matrix', [
                'grouped' => $permissions,
                'assigned' => $assignedPermissions,
                'name' => 'permissions[]',
            ])
        </div>
    </section>
</x-modal>
