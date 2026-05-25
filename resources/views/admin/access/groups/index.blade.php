@extends('layouts.app')

@section('title', __('access.title.groups'))
@section('nav-title', __('access.title.groups'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Gruppen für Berechtigungs-Bündelung verwalten.')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.access.groups.create')"
                            show-label>{{ __('access.action.group_new') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('access.field.group_name') }}</th>
                <th>{{ __('access.field.group_slug') }}</th>
                <th>{{ __('access.field.member_count') }}</th>
                <th>{{ __('access.field.description') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($groups as $group)
            <tr>
                <td class="font-medium">
                    @if ($group->color)
                        <span class="inline-block w-2 h-2 rounded-full mr-2" style="background-color: {{ $group->color }}"></span>
                    @endif
                    {{ $group->name }}
                    @if ($group->is_system)
                        <span class="badge badge-xs badge-info ml-2">{{ __('access.badge.system') }}</span>
                    @endif
                </td>
                <td class="font-mono text-xs text-base-content/60">{{ $group->slug }}</td>
                <td>{{ $group->members_count }}</td>
                <td class="text-sm text-base-content/70">{{ $group->description }}</td>
                <td class="text-right">
                    <x-icon-btn icon="visibility" size="xs" :href="route('admin.access.groups.show', $group)"
                                :title="__('access.action.view')" />
                    <x-icon-btn icon="edit" size="xs"
                                data-entry-modal-trigger
                                :href="route('admin.access.groups.edit', $group)"
                                :title="__('access.action.edit')" />
                    @unless ($group->is_system)
                        <form method="POST" action="{{ route('admin.access.groups.destroy', $group) }}" class="inline">
                            @csrf @method('DELETE')
                            <x-icon-btn type="submit" icon="delete" size="xs" tone="error"
                                        :title="__('access.action.delete')"
                                        data-confirm="{{ __('access.confirm.group_delete') }}" />
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5"
                icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>'
                :title="__('access.empty.groups')" compact />
        @endforelse
    </x-table>

    {{ $groups->links() }}
</x-page-shell>
@endsection
