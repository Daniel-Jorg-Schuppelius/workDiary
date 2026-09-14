{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : registrations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LTI-1.3-Registrierungen (Feature 149): Tools, die WorkDiary startet, und
  Plattformen, die WorkDiary starten. Variablen: $tools, $platforms, $issuer
--}}
@extends('layouts.app')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('title', __('learning.lti_registration.title'))
@section('nav-title', __('learning.lti_registration.title'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('learning.lti_registration.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('learning.lti-registrations.tools.create')"
                            show-label>{{ __('learning.lti_registration.create_tool') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="ghost" size="sm"
                            data-entry-modal-trigger
                            :href="route('learning.lti-registrations.platforms.create')"
                            show-label>{{ __('learning.lti_registration.create_platform') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <h3 class="mb-1 text-sm font-semibold">{{ __('learning.lti_registration.platform_details') }}</h3>
        <p class="mb-2 text-xs text-muted">{{ __('learning.lti_registration.platform_details_help') }}</p>
        <x-detail-grid>
            <x-detail-grid.row :label="__('learning.lti_registration.issuer')"><span class="break-all font-mono text-xs">{{ $issuer }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('learning.lti_registration.jwks_url')"><span class="break-all font-mono text-xs">{{ route('learning.lti.jwks') }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('learning.lti_registration.auth_url')"><span class="break-all font-mono text-xs">{{ route('learning.lti.platform.auth') }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('learning.lti_registration.deep_linking_return')"><span class="break-all font-mono text-xs">{{ route('learning.lti.deep-linking.return') }}</span></x-detail-grid.row>
        </x-detail-grid>
    </x-card>

    <x-card>
        <h3 class="mb-1 text-sm font-semibold">{{ __('learning.lti_registration.tool_details') }}</h3>
        <p class="mb-2 text-xs text-muted">{{ __('learning.lti_registration.tool_details_help') }}</p>
        <x-detail-grid>
            <x-detail-grid.row :label="__('learning.lti_registration.tool_login_url')"><span class="break-all font-mono text-xs">{{ route('learning.lti.tool.login') }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('learning.lti_registration.tool_launch_url')"><span class="break-all font-mono text-xs">{{ route('learning.lti.tool.launch') }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('learning.lti_registration.tool_deep_linking_url')"><span class="break-all font-mono text-xs">{{ route('learning.lti.tool.launch') }}</span></x-detail-grid.row>
            <x-detail-grid.row :label="__('learning.lti_registration.jwks_url')"><span class="break-all font-mono text-xs">{{ route('learning.lti.jwks') }}</span></x-detail-grid.row>
        </x-detail-grid>
    </x-card>

    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('learning.lti_registration.tools') }}</h3>
        <x-table :bare="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string" default>{{ __('learning.lti_registration.name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('learning.lti_registration.client_id') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('learning.lti_registration.launch_url') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('learning.lti_registration.is_active') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($tools as $tool)
                <tr class="hover">
                    <td class="font-medium">{{ $tool->name }}</td>
                    <td class="font-mono text-xs">{{ $tool->client_id }}</td>
                    <td class="break-all text-xs">{{ $tool->launch_url }}</td>
                    <td class="text-sm">
                        <x-status-badge :tone="$tool->is_active ? 'success' : 'neutral'" size="sm" outline>{{ $tool->is_active ? __('learning.lti_registration.status_active') : __('learning.lti_registration.status_inactive') }}</x-status-badge>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('learning.lti-registrations.tools.edit', $tool)" :label="__('learning.lti_registration.edit')" />
                            <form method="POST" action="{{ route('learning.lti-registrations.tools.destroy', $tool) }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('learning.lti_registration.delete')"
                                            data-confirm-dialog data-confirm-message="{{ __('learning.lti_registration.delete_confirm') }}" data-confirm-tone="error" />
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="hub" :colspan="5" :title="__('learning.lti_registration.empty_tools')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('learning.lti_registration.platforms') }}</h3>
        <x-table :bare="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string" default>{{ __('learning.lti_registration.name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('learning.lti_registration.issuer') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('learning.lti_registration.client_id') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('learning.lti_registration.is_active') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($platforms as $platform)
                <tr class="hover">
                    <td class="font-medium">{{ $platform->name }}</td>
                    <td class="break-all text-xs">{{ $platform->issuer }}</td>
                    <td class="font-mono text-xs">{{ $platform->client_id }}</td>
                    <td class="text-sm">
                        <x-status-badge :tone="$platform->is_active ? 'success' : 'neutral'" size="sm" outline>{{ $platform->is_active ? __('learning.lti_registration.status_active') : __('learning.lti_registration.status_inactive') }}</x-status-badge>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('learning.lti-registrations.platforms.edit', $platform)" :label="__('learning.lti_registration.edit')" />
                            <form method="POST" action="{{ route('learning.lti-registrations.platforms.destroy', $platform) }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('learning.lti_registration.delete')"
                                            data-confirm-dialog data-confirm-message="{{ __('learning.lti_registration.delete_confirm') }}" data-confirm-tone="error" />
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="hub" :colspan="5" :title="__('learning.lti_registration.empty_platforms')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
