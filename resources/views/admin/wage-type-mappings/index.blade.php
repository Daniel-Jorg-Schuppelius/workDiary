{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('wage_types.title.index'))
@section('nav-title', __('wage_types.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('wage_types.title.index_subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.wage-type-mappings.create')"
                        show-label>{{ __('wage_types.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
    @endif

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('wage_types.title.mappings_help') }}</h3>
            <div class="text-sm">{{ __('wage_types.title.mappings_help_text') }}</div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('wage_types.field.profile') }}</th>
                <th>{{ __('wage_types.field.wage_type') }}</th>
                <th>{{ __('wage_types.field.external_code') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($mappings as $mapping)
            @php /** @var \App\Models\WageTypeMapping $mapping */ @endphp
            <tr>
                <td>{{ $profiles[$mapping->profile] ?? $mapping->profile }}</td>
                <td class="font-mono text-sm">{{ $mapping->wage_type }}</td>
                <td class="font-mono text-sm">{{ $mapping->external_code }}</td>
                <td class="text-right">
                    @if ($canManage ?? false)
                        <x-icon-btn icon="edit" tone="ghost" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('admin.wage-type-mappings.edit', $mapping)"
                                    :label="__('wage_types.action.edit')" />
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="badge" :label="__('wage_types.title.empty')" />
        @endforelse
    </x-table>

    {{-- Automatische Lieferung je Export-Profil (A21) --}}
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('wage_types.title.delivery') }}</h3>
            <p class="text-sm text-base-content/60">{{ __('wage_types.title.delivery_help_text') }}</p>
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('wage_types.field.profile') }}</th>
                        <th>{{ __('wage_types.field.mail') }}</th>
                        <th>{{ __('wage_types.field.sftp') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($profiles as $key => $label)
                    @php /** @var \App\Models\TimeExportDeliveryConfig|null $cfg */ $cfg = $deliveryConfigs[$key] ?? null; @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td>
                            @if ($cfg?->mail_enabled && $cfg->mailRecipients() !== [])
                                <x-status-badge size="xs" tone="success">{{ __('wage_types.field.enabled') }}</x-status-badge>
                                <span class="text-xs text-base-content/60">{{ implode(', ', $cfg->mailRecipients()) }}</span>
                            @else
                                <x-status-badge size="xs" tone="ghost">{{ __('wage_types.field.disabled') }}</x-status-badge>
                            @endif
                        </td>
                        <td>
                            @if ($cfg?->sftp_enabled)
                                <x-status-badge size="xs" tone="success">{{ __('wage_types.field.enabled') }}</x-status-badge>
                                <span class="font-mono text-xs text-base-content/60">{{ $cfg->sftp_username }}&#64;{{ $cfg->sftp_host }}:{{ $cfg->sftp_port }}</span>
                            @else
                                <x-status-badge size="xs" tone="ghost">{{ __('wage_types.field.disabled') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($canManage ?? false)
                                <x-icon-btn icon="settings" tone="ghost" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('admin.wage-type-mappings.delivery.edit', ['profile' => $key])"
                                            :label="__('wage_types.action.configure')" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    </div>
</x-index-page>
@endsection
