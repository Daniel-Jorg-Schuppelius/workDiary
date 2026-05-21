@extends('layouts.app')

@section('title', __('access.title.member_edit', ['name' => $member->name]))
@section('nav-title', $member->name)

@section('content')
<x-page-shell gap="6">
    <form method="POST" action="{{ route('admin.access.members.update', $member) }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex flex-wrap items-baseline gap-3">
                    <h2 class="text-lg font-semibold">{{ $member->name }}</h2>
                    <span class="text-sm text-base-content/60">{{ $member->email }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-3">
                    <h3 class="card-title">{{ __('access.title.assigned_roles') }}</h3>
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
                        <p class="text-sm text-base-content/60">{{ __('access.empty.roles') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-3">
                    <h3 class="card-title">{{ __('access.title.assigned_groups') }}</h3>
                    @forelse ($groups as $group)
                        <label class="label cursor-pointer justify-start gap-3 hover:bg-base-200 rounded px-2">
                            <input type="checkbox" name="groups[]" value="{{ $group->id }}"
                                   class="checkbox checkbox-sm"
                                   @checked(in_array($group->id, $assignedGroups, true)) />
                            <span class="text-sm">
                                {{ $group->name }}
                                @if ($group->description)
                                    <span class="block text-xs text-base-content/50">{{ $group->description }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-base-content/60">{{ __('access.empty.groups') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-3">
                <h3 class="card-title flex items-center gap-2">
                    <x-icon name="lock_open" />
                    {{ __('access.title.effective_permissions') }}
                    <span class="badge badge-ghost badge-sm">{{ $effectivePermissions->count() }}</span>
                </h3>
                <p class="text-sm text-base-content/60">{{ __('access.hint.effective_permissions') }}</p>
                @if ($effectivePermissions->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('access.empty.effective_permissions') }}</p>
                @else
                    <div class="flex flex-wrap gap-1">
                        @foreach ($effectivePermissions as $permission)
                            <span class="badge badge-sm badge-ghost font-mono">{{ $permission }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.access.members.index') }}" class="btn btn-ghost btn-sm">{{ __('access.action.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('access.action.save') }}</button>
        </div>
    </form>
</x-page-shell>
@endsection
