@extends('layouts.app')

@section('title', __('access.title.members'))
@section('nav-title', __('access.title.members'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <form method="GET" action="{{ route('admin.access.members.index') }}" class="join">
                    <input type="text" name="q" value="{{ $search ?? '' }}"
                           placeholder="{{ __('access.placeholder.search_members') }}"
                           class="input input-sm input-bordered join-item" />
                    <button class="btn btn-sm join-item">{{ __('access.action.search') }}</button>
                </form>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table table-sort="server"
             :route="route('admin.access.members.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="name">{{ __('access.field.member') }}</x-table.th>
                <x-table.th sort="email">{{ __('access.field.email') }}</x-table.th>
                <th>{{ __('access.field.roles') }}</th>
                <th>{{ __('access.field.groups') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($members as $member)
            <tr>
                <td class="font-medium">{{ $member->name }}</td>
                <td class="text-sm text-base-content/70">{{ $member->email }}</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($member->roles as $role)
                            <span class="badge badge-sm badge-outline">{{ $role->name }}</span>
                        @endforeach
                    </div>
                </td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($member->userGroups as $group)
                            <span class="badge badge-sm badge-ghost"
                                  @if ($group->color) style="border-color: {{ $group->color }}" @endif>
                                {{ $group->name }}
                            </span>
                        @endforeach
                    </div>
                </td>
                <td class="text-right">
                    <x-icon-btn icon="manage_accounts" size="xs"
                                :href="route('admin.access.members.edit', $member)"
                                :title="__('access.action.edit_assignments')" />
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('access.empty.members') }}</td></tr>
        @endforelse
    </x-table>

    {{ $members->links() }}
</x-page-shell>
@endsection
