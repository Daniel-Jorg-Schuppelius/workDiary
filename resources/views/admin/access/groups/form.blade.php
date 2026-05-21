@extends('layouts.app')

@php($isEdit = (bool) ($group->id ?? false))

@section('title', $isEdit ? __('access.title.group_edit', ['name' => $group->name]) : __('access.title.group_new'))
@section('nav-title', $isEdit ? __('access.title.group_edit', ['name' => $group->name]) : __('access.title.group_new'))

@section('content')
<x-page-shell gap="6">
    <form method="POST"
          action="{{ $isEdit ? route('admin.access.groups.update', $group) : route('admin.access.groups.store') }}"
          class="space-y-6">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form-group :label="__('access.field.group_name')" name="name" required>
                    <input type="text" name="name" value="{{ old('name', $group->name) }}"
                           class="input input-bordered w-full" maxlength="120" required />
                </x-form-group>

                <x-form-group :label="__('access.field.color')" name="color">
                    <input type="color" name="color" value="{{ old('color', $group->color ?? '#6b7280') }}"
                           class="input input-bordered w-full h-10" />
                </x-form-group>

                <x-form-group :label="__('access.field.description')" name="description" class="md:col-span-2">
                    <textarea name="description" rows="2" maxlength="500"
                              class="textarea textarea-bordered w-full">{{ old('description', $group->description) }}</textarea>
                </x-form-group>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h3 class="text-lg font-semibold">{{ __('access.title.assigned_roles') }}</h3>
                <p class="text-sm text-base-content/60">{{ __('access.help.group_roles') }}</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @forelse ($roles as $role)
                        <label class="label cursor-pointer justify-start gap-3 hover:bg-base-200 rounded px-2">
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
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h3 class="text-lg font-semibold">{{ __('access.title.direct_permissions') }}</h3>
                <p class="text-sm text-base-content/60">{{ __('access.help.group_permissions') }}</p>

                @include('admin.access._permission_matrix', [
                    'grouped' => $permissions,
                    'assigned' => $assignedPermissions,
                    'name' => 'permissions[]',
                ])
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.access.groups.index') }}" class="btn btn-ghost btn-sm">{{ __('access.action.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">
                {{ $isEdit ? __('access.action.save') : __('access.action.create') }}
            </button>
        </div>
    </form>
</x-page-shell>
@endsection
