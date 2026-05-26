@extends('layouts.app')

@section('title', __('access.title.roles'))
@section('nav-title', __('access.title.roles'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Organisations- und Plattform-Rollen für :org verwalten.', ['org' => $organization->name])">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.access.roles.create')"
                            show-label>{{ __('access.action.role_new') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">{{ __('access.title.org_roles', ['org' => $organization->name]) }}</h2>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('access.field.role_name') }}</th>
                    <th>{{ __('access.field.permission_count') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($orgRoles as $role)
                <tr>
                    <td class="font-mono text-sm">
                        {{ $role->name }}
                        @if (in_array($role->name, $systemRoleNames, true))
                            <span class="badge badge-xs badge-info ml-2">{{ __('access.badge.system') }}</span>
                        @endif
                    </td>
                    <td>{{ $role->permissions_count }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="edit" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('admin.access.roles.edit', $role)"
                                    :title="__('access.action.edit')" />
                        @if (! in_array($role->name, $systemRoleNames, true))
                            <form method="POST" action="{{ route('admin.access.roles.destroy', $role) }}" class="inline">
                                @csrf @method('DELETE')
                                <x-icon-btn type="submit" icon="delete" size="xs" tone="error"
                                            :title="__('access.action.delete')"
                                            data-confirm="{{ __('access.confirm.role_delete') }}" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-base-content/60 py-6">{{ __('access.empty.roles') }}</td></tr>
            @endforelse
        </x-table>
    </section>

    @if ($globalRoles->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">{{ __('access.title.global_roles') }}</h2>
            <p class="text-sm text-base-content/60">{{ __('access.hint.global_roles') }}</p>
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('access.field.role_name') }}</th>
                        <th>{{ __('access.field.permission_count') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($globalRoles as $role)
                    <tr>
                        <td class="font-mono text-sm">{{ $role->name }}</td>
                        <td>{{ $role->permissions_count }}</td>
                    </tr>
                @endforeach
            </x-table>
        </section>
    @endif
</x-page-shell>
@endsection
