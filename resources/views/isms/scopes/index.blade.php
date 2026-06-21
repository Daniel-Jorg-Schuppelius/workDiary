{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Minimal-Verwaltung Geltungsbereiche (Feature 046): Liste + Modal-CRUD,
  nur isms.manage. Der Default-Scope („Gesamtorganisation") ist nicht
  löschbar (Policy + Serviceregel).
--}}

@extends('layouts.app')

@section('title', __('isms.title.scopes'))
@section('nav-title', __('isms.title.scopes'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.scopes')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.scopes.create')"
                            show-label>{{ __('isms.action.create_scope') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.name') }}</th>
                    <th>{{ __('isms.field.description') }}</th>
                    <th class="text-center">{{ __('isms.field.statements') }}</th>
                    <th class="text-center">{{ __('isms.title.risks') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($scopes as $scope)
                <tr class="hover" id="isms-scope-{{ $scope->id }}">
                    <td>
                        <span class="font-medium">{{ $scope->name }}</span>
                        @if ($scope->is_default)
                            <x-status-badge tone="info" outline>{{ __('isms.scope.default_badge') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="max-w-md truncate text-base-content/70" title="{{ $scope->description }}">{{ $scope->description ?? '—' }}</td>
                    <td class="text-center text-base-content/70">{{ $scope->statements_count }}</td>
                    <td class="text-center text-base-content/70">{{ $scope->risks_count }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $scope)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.scopes.edit', $scope)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @if (! $scope->is_default)
                                @can('delete', $scope)
                                    <x-action-form :action="route('isms.scopes.destroy', $scope)" method="DELETE"
                                          data-confirm-title="{{ __('isms.action.delete') }}"
                                          :confirm="__('isms.confirm_delete_scope')"
                                          confirm-icon="delete"
                                          confirm-tone="error"
                                          :confirm-label="__('isms.action.delete')">
                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                    :label="__('isms.action.delete')" />
                                    </x-action-form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5"
                               :title="__('isms.empty_scopes_title')"
                               :message="__('isms.empty_scopes')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
