{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('access.title.groups'))
@section('nav-title', __('access.title.groups'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Gruppen für Berechtigungs-Bündelung verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.access.groups.create')"
                    show-label>{{ __('access.action.group_new') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('admin.access.groups.index')" :reset="route('admin.access.groups.index')">
        <input type="text" name="q" value="{{ $search ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('admin.access.groups.index')" :current-sort="$sort" :current-dir="$dir"
             :sort-params="array_filter(['q' => $search ?: null])">
        <x-slot:head>
            <tr>
                <x-table.th sort="name">{{ __('access.field.group_name') }}</x-table.th>
                <x-table.th sort="slug">{{ __('access.field.group_slug') }}</x-table.th>
                <x-table.th sort="members_count">{{ __('access.field.member_count') }}</x-table.th>
                <x-table.th sort="description">{{ __('access.field.description') }}</x-table.th>
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
                        <x-status-badge tone="info" size="xs" class="ml-2">{{ __('access.badge.system') }}</x-status-badge>
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

    <x-pagination :paginator="$groups" standing />
</x-index-page>
@endsection
