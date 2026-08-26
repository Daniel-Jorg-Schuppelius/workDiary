{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('access.title.roles'))
@section('nav-title', __('access.title.roles'))

@section('content')
<x-index-page :subtitle="__('Organisations- und Plattform-Rollen für :org verwalten.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.access.roles.create')"
                    show-label>{{ __('access.action.role_new') }}</x-icon-btn>
    </x-slot:actions>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">{{ __('access.title.org_roles', ['org' => $organization->name]) }}</h2>
        <x-table table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('access.field.role_name') }}</x-table.th>
                    <x-table.th sort type="number">{{ __('access.field.permission_count') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($orgRoles as $role)
                <tr>
                    <td class="text-sm" data-sort-value="{{ $role->name }}">
                        {{ \Illuminate\Support\Facades\Lang::has("user.role.{$role->name}") ? __("user.role.{$role->name}") : $role->name }}
                        @if (in_array($role->name, $systemRoleNames, true))
                            <x-status-badge tone="info" size="xs" class="ml-2">{{ __('access.badge.system') }}</x-status-badge>
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
                <x-table.empty :colspan="3" :title="__('access.empty.roles')" />
            @endforelse
        </x-table>
    </section>

    @if ($globalRoles->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">{{ __('access.title.global_roles') }}</h2>
            <p class="text-sm text-muted">{{ __('access.hint.global_roles') }}</p>
            <x-table table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('access.field.role_name') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('access.field.permission_count') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($globalRoles as $role)
                    <tr>
                        <td class="text-sm" data-sort-value="{{ $role->name }}">{{ \Illuminate\Support\Facades\Lang::has("user.role.{$role->name}") ? __("user.role.{$role->name}") : $role->name }}</td>
                        <td>{{ $role->permissions_count }}</td>
                    </tr>
                @endforeach
            </x-table>
        </section>
    @endif
</x-index-page>
@endsection
