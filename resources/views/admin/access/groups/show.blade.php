{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('access.title.group_show', ['name' => $group->name]))
@section('nav-title', $group->name)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <x-icon-btn icon="edit" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.access.groups.edit', $group)" show-label>
                    {{ __('access.action.edit') }}
                </x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h3 class="card-title">{{ __('access.title.metadata') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-base-content/60">{{ __('access.field.group_name') }}</dt><dd>{{ $group->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-base-content/60">{{ __('access.field.group_slug') }}</dt><dd class="font-mono text-xs">{{ $group->slug }}</dd></div>
                    @if ($group->description)
                        <div><dt class="text-base-content/60">{{ __('access.field.description') }}</dt><dd>{{ $group->description }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-3">
                <h3 class="card-title">{{ __('access.title.assigned_roles') }}</h3>
                @forelse ($group->roles as $role)
                    <x-status-badge size="md" outline>{{ $role->name }}</x-status-badge>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('access.empty.assigned_roles') }}</p>
                @endforelse

                <h3 class="card-title mt-4">{{ __('access.title.direct_permissions') }}</h3>
                @if ($group->permissions->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('access.empty.direct_permissions') }}</p>
                @else
                    <div class="flex flex-wrap gap-1">
                        @foreach ($group->permissions as $permission)
                            <x-status-badge tone="ghost" size="sm" class="font-mono">{{ $permission->name }}</x-status-badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="card-title">{{ __('access.title.members') }} ({{ $group->members->count() }})</h3>
                @if ($addableUsers->isNotEmpty())
                    <x-icon-btn icon="person_add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('admin.access.groups.members.attach.form', $group)"
                                show-label>{{ __('access.field.add_member') }}</x-icon-btn>
                @endif
            </div>

            <x-table table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('access.field.member') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('access.field.email') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('access.field.joined_at') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($group->members as $member)
                    <tr>
                        <td>{{ $member->name }}</td>
                        <td class="text-sm text-base-content/70">{{ $member->email }}</td>
                        <td class="text-sm" @if ($member->pivot->joined_at) data-sort-value="{{ \Carbon\Carbon::parse($member->pivot->joined_at)->format('Y-m-d') }}" @endif>{{ optional($member->pivot->joined_at)->format('d.m.Y') }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.access.groups.members.detach', [$group, $member]) }}" class="inline">
                                @csrf @method('DELETE')
                                <x-icon-btn type="submit" icon="person_remove" size="xs" tone="error"
                                            :title="__('access.action.remove_member')" />
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-base-content/60 py-4">{{ __('access.empty.members') }}</td></tr>
                @endforelse
            </x-table>
        </div>
    </div>
</x-page-shell>
@endsection
