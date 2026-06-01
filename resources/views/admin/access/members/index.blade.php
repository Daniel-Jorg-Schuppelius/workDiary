@extends('layouts.app')

@section('title', __('access.title.members'))
@section('nav-title', __('access.title.members'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Mitgliedschaften und Rollen-Zuweisungen pro Organisation verwalten.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.access.members.index') }}" class="join">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   placeholder="{{ __('access.placeholder.search_members') }}"
                   class="input input-sm input-bordered join-item" />
            <button class="btn btn-sm join-item">{{ __('access.action.search') }}</button>
        </form>
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
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
                            <x-status-badge size="sm" outline>{{ $role->name }}</x-status-badge>
                        @endforeach
                    </div>
                </td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($member->userGroups as $group)
                            <x-status-badge tone="ghost" size="sm"
                                  :style="$group->color ? 'border-color: '.$group->color : null">
                                {{ $group->name }}
                            </x-status-badge>
                        @endforeach
                    </div>
                </td>
                <td class="text-right">
                    <x-icon-btn icon="manage_accounts" size="xs"
                                data-entry-modal-trigger
                                :href="route('admin.access.members.edit', $member)"
                                :title="__('access.action.edit_assignments')" />
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5"
                icon='<span class="material-symbols-outlined" aria-hidden="true">person</span>'
                :title="__('access.empty.members')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$members" />
</x-index-page>
@endsection
