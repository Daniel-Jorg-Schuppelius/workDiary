{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Eigene Arbeitsbereiche (Feature 082 Phase 2, MVP-731): Liste der
    persönlichen Fokus-Zusammenstellungen. Anlegen/Bearbeiten laufen als
    Modal (_form_dialog) über data-entry-modal-trigger.
--}}
@extends('layouts.app')

@section('title', __('scope.workspace.title'))
@section('nav-title', __('scope.workspace.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\UserWorkspace> $workspaces */
    /** @var string $activeKey */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('scope.workspace.subtitle')">
        <x-slot:actions>
            <x-button href="{{ route('me.navigation.customize') }}" tone="ghost" size="sm" icon="edit">{{ __('scope.nav.customize') }}</x-button>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('me.workspaces.create')"
                        show-label>{{ __('scope.workspace.create') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="number" align="right" class="w-16">{{ __('scope.workspace.sort') }}</x-table.th>
                    <th class="w-12"></th>
                    <x-table.th sort type="string">{{ __('scope.workspace.name') }}</x-table.th>
                    <x-table.th sort type="number" align="right" class="w-40">{{ __('scope.workspace.items') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($workspaces as $workspace)
                    @php $key = \App\Services\Navigation\NavFocusService::personalKey($workspace); @endphp
                    <tr class="hover">
                        <td class="text-right tabular-nums">{{ $workspace->sort }}</td>
                        <td><x-icon name="{{ $workspace->icon ?: 'dashboard_customize' }}" /></td>
                        <td class="font-semibold">
                            {{ $workspace->name }}
                            @if ($key === $activeKey)
                                <span class="badge badge-primary badge-sm ml-2">{{ __('scope.workspace.active') }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ count($workspace->keys()) }}</td>
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="play_arrow"
                                        tone="primary"
                                        data-workspace-activate
                                        data-url="{{ route('me.focus.switch', $key) }}"
                                        :label="__('scope.focus.switcher')" />
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('me.workspaces.edit', $workspace)"
                                        :label="__('scope.workspace.edit')" />
                            <x-action-form :action="route('me.workspaces.destroy', $workspace)"
                                  method="DELETE"
                                  data-confirm-title="{{ __('scope.workspace.delete_title') }}"
                                  :confirm="__('scope.workspace.delete_confirm')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5"
                        icon="dashboard_customize"
                        :title="__('scope.workspace.empty')" compact />
                @endforelse
            </tbody>
        </x-table>
    </x-index-page>
@endsection
