{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Abteilungen/Sparten (Feature 159, MVP-842): Referenzliste mit Gruppenzahl
  und Dialog; Löschen nur ohne Gruppen.
--}}
@extends('layouts.app')
@section('title', __('club.title.departments'))
@section('nav-title', __('club.title.departments'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.subtitle.departments')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('club.departments.create')"
                        show-label>{{ __('club.action.create_department') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.name') }}</th>
                <th>{{ __('club.field.discipline') }}</th>
                <th>{{ __('club.field.description') }}</th>
                <th class="text-center">{{ __('club.field.groups') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($departments as $department)
            <tr class="hover">
                <td class="font-medium">{{ $department->name }}</td>
                <td class="text-sm">{{ $department->discipline ?? '–' }}</td>
                <td class="text-sm text-base-content/70">{{ $department->description ?? '–' }}</td>
                <td class="text-center text-sm">{{ $department->groups_count }}</td>
                <td>
                    <x-status-badge :tone="$department->is_active ? 'success' : 'ghost'" size="sm">{{ $department->is_active ? __('club.field.is_active') : __('club.label.inactive') }}</x-status-badge>
                </td>
                <td class="text-right">
                    @if ($canManage)
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('club.departments.edit', $department)"
                                        :label="__('club.action.edit')" />
                            @if ($department->groups_count === 0)
                                <x-action-form :action="route('club.departments.destroy', $department)" method="DELETE" :confirm="__('club.confirm.delete_department')" confirm-icon="delete" confirm-tone="error">
                                    <x-icon-btn type="submit" icon="delete" tone="error" size="xs" :label="__('club.action.delete')" />
                                </x-action-form>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="account_tree" :colspan="6" :title="__('club.empty.departments')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$departments" standing />
</x-index-page>
@endsection
