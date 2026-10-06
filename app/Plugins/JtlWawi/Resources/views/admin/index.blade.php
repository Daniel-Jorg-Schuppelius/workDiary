{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('jtl_wawi::jtl_wawi.title'))
@section('nav-title', __('jtl_wawi::jtl_wawi.title'))

@section('content')
<x-index-page :subtitle="__('jtl_wawi::jtl_wawi.intro')">
    <x-slot:badges>
        @if ($connection)
            <x-status-badge>{{ __('jtl_wawi::jtl_wawi.mode.' . $connection->mode) }}</x-status-badge>
            @if ($connection->status === \App\Plugins\JtlWawi\Enums\JtlConnectionStatus::Active)
                <x-status-badge tone="success">{{ __('jtl_wawi::jtl_wawi.status.active') }}</x-status-badge>
            @elseif ($connection->status === \App\Plugins\JtlWawi\Enums\JtlConnectionStatus::PendingRegistration)
                <x-status-badge tone="warning">{{ __('jtl_wawi::jtl_wawi.status.pending_registration') }}</x-status-badge>
            @elseif ($connection->status === \App\Plugins\JtlWawi\Enums\JtlConnectionStatus::Blocked)
                <x-status-badge tone="error">{{ __('jtl_wawi::jtl_wawi.status.blocked') }}: {{ $connection->blocked_reason }}</x-status-badge>
            @else
                <x-status-badge>{{ $connection->status->label() }}</x-status-badge>
            @endif
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status-Karte --}}
    <x-card>
        <p class="mb-3 text-xs text-warning">{{ __('jtl_wawi::jtl_wawi.beta_notice') }}</p>

        @if ($connection)
            <x-detail-grid layout="split" :cols="2" class="mb-3">
                @if ($connection->detected_version)
                    <x-detail-grid.row :label="__('jtl_wawi::jtl_wawi.field.detected_version')">{{ $connection->detected_version }}</x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('jtl_wawi::jtl_wawi.field.api_version')">{{ $connection->api_version }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('jtl_wawi::jtl_wawi.field.last_sync')">{{ $connection->last_sync_at?->diffForHumans() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('jtl_wawi::jtl_wawi.stats.linked_articles')">{{ $linkedArticles }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('jtl_wawi::jtl_wawi.stats.open_inbox')">
                    @if ($openInbox > 0)
                        <a href="{{ route('admin.integration.inbox', ['plugin' => \App\Plugins\JtlWawi\JtlWawiPlugin::ID]) }}" class="link">{{ $openInbox }}</a>
                    @else
                        0
                    @endif
                </x-detail-grid.row>
                @if ($connection->last_error)
                    <x-detail-grid.row :label="__('jtl_wawi::jtl_wawi.field.last_error')" full class="text-error">{{ $connection->last_error }}</x-detail-grid.row>
                @endif
            </x-detail-grid>

            @if ($scopeCheck && ! $scopeCheck['ok'] && ! $scopeCheck['unknown'])
                <div role="alert" class="alert alert-warning text-sm">
                    {{ __('jtl_wawi::jtl_wawi.scopes.missing', ['scopes' => implode(', ', $scopeCheck['missing_read'])]) }}
                </div>
            @endif
            @if ($scopeCheck && ($scopeCheck['missing_write'] ?? []) !== [] && ! $scopeCheck['unknown'])
                <p class="text-xs text-muted">{{ __('jtl_wawi::jtl_wawi.scopes.missing_write', ['scopes' => implode(', ', $scopeCheck['missing_write'])]) }}</p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($connection->isActive())
                    <form method="POST" action="{{ route('admin.jtl.sync') }}">
                        @csrf
                        <x-button type="submit">{{ __('jtl_wawi::jtl_wawi.action.sync_now') }}</x-button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.jtl.connection.disconnect') }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('jtl_wawi::jtl_wawi.confirm.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('jtl_wawi::jtl_wawi.action.disconnect') }}</x-button>
                </form>
            </div>

            @if (is_array($connection->last_sync_counters) && $connection->last_sync_counters !== [])
                <x-table bare size="xs" class="mt-3">
                    <x-slot:head>
                        <tr><th>{{ __('jtl_wawi::jtl_wawi.sync.section') }}</th><th>{{ __('jtl_wawi::jtl_wawi.sync.counters') }}</th></tr>
                    </x-slot:head>
                    @foreach ($connection->last_sync_counters as $section => $counters)
                        <tr>
                            <td>{{ __('jtl_wawi::jtl_wawi.sync.' . $section) }}</td>
                            <td class="font-mono text-xs">
                                @foreach ((array) $counters as $key => $value)
                                    <span class="mr-2">{{ $key }}: {{ is_bool($value) ? ($value ? '✓' : '—') : $value }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        @endif
    </x-card>

    {{-- OnPremise-Registrierung --}}
    @if ($connection && $connection->isOnPremise() && ! $connection->isActive())
        <x-card class="space-y-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi::jtl_wawi.registration.heading') }}</h2>
            @if ($connection->status === \App\Plugins\JtlWawi\Enums\JtlConnectionStatus::PendingRegistration)
                <div role="status" class="alert alert-info text-sm">{{ __('jtl_wawi::jtl_wawi.registration.waiting') }}</div>
                <form method="POST" action="{{ route('admin.jtl.connection.check') }}">
                    @csrf
                    <x-button type="submit">{{ __('jtl_wawi::jtl_wawi.action.check_registration') }}</x-button>
                </form>
            @else
                <p class="text-sm text-muted">{{ __('jtl_wawi::jtl_wawi.registration.explain') }}</p>
                <form method="POST" action="{{ route('admin.jtl.connection.register') }}">
                    @csrf
                    <x-button type="submit">{{ __('jtl_wawi::jtl_wawi.action.start_registration') }}</x-button>
                </form>
            @endif
        </x-card>
    @endif

    {{-- Verbindung --}}
    <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.jtl.connection.store') }}"
            x-data="{ mode: {{ \Illuminate\Support\Js::from(old('mode', $connection->mode ?? \App\Plugins\JtlWawi\Models\JtlConnection::MODE_ON_PREMISE)) }} }">
        @csrf
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi::jtl_wawi.connection.heading') }}</h2>

        <div class="flex flex-wrap gap-4">
            <label class="label cursor-pointer justify-start gap-2">
                <input type="radio" name="mode" value="on_premise" class="radio radio-sm" x-model="mode">
                <span class="label-text">{{ __('jtl_wawi::jtl_wawi.mode.on_premise') }}</span>
            </label>
            <label class="label cursor-pointer justify-start gap-2">
                <input type="radio" name="mode" value="cloud" class="radio radio-sm" x-model="mode">
                <span class="label-text">{{ __('jtl_wawi::jtl_wawi.mode.cloud') }}</span>
            </label>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <template x-if="mode === 'on_premise'">
                <label class="form-control md:col-span-2">
                    <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.base_url') }}</span>
                    <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                           placeholder="https://wawi.example.local:5883/api/eazybusiness" class="input input-bordered input-sm">
                    <span class="label-text-alt text-muted">{{ __('jtl_wawi::jtl_wawi.field.base_url_help') }}</span>
                </label>
            </template>

            <label class="form-control">
                <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.api_version') }}</span>
                <select name="api_version" class="select select-bordered select-sm">
                    @foreach (['2.0', '2.1'] as $version)
                        <option value="{{ $version }}" @selected(old('api_version', $connection->api_version ?? '2.0') === $version)>{{ $version }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.company_id') }}</span>
                <input type="text" name="company_id" value="{{ old('company_id', $connection->company_id ?? '') }}"
                       class="input input-bordered input-sm" autocomplete="off">
                <span class="label-text-alt text-muted">{{ __('jtl_wawi::jtl_wawi.field.company_id_help') }}</span>
            </label>

            <template x-if="mode === 'on_premise'">
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="allow_private_network" value="0">
                    <input type="checkbox" name="allow_private_network" value="1" class="toggle toggle-sm toggle-warning"
                           @checked(old('allow_private_network', $connection->allow_private_network ?? false))>
                    <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.allow_private_network') }}</span>
                </label>
            </template>

            <template x-if="mode === 'cloud'">
                <label class="form-control">
                    <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.tenant_id') }}</span>
                    <input type="text" name="tenant_id" value="{{ old('tenant_id', $connection->tenant_id ?? '') }}"
                           class="input input-bordered input-sm" autocomplete="off">
                </label>
            </template>
            <template x-if="mode === 'cloud'">
                <label class="form-control">
                    <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.client_id') }}</span>
                    <input type="password" name="client_id" autocomplete="new-password"
                           placeholder="{{ $connection && $connection->hasCredentials() ? __('jtl_wawi::jtl_wawi.field.secret_keep') : '' }}"
                           class="input input-bordered input-sm">
                </label>
            </template>
            <template x-if="mode === 'cloud'">
                <label class="form-control">
                    <span class="label-text">{{ __('jtl_wawi::jtl_wawi.field.client_secret') }}</span>
                    <input type="password" name="client_secret" autocomplete="new-password"
                           placeholder="{{ $connection && $connection->hasCredentials() ? __('jtl_wawi::jtl_wawi.field.secret_keep') : '' }}"
                           class="input input-bordered input-sm">
                </label>
            </template>
        </div>

        <template x-if="mode === 'on_premise'">
            <p class="text-xs text-muted">{{ __('jtl_wawi::jtl_wawi.field.allow_private_network_help') }}</p>
        </template>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('jtl_wawi::jtl_wawi.action.save') }}</x-button>
        </div>
    </x-card>

    {{-- Lager-Zuordnung --}}
    <x-card class="space-y-3">
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi::jtl_wawi.warehouses.heading') }}</h2>
        @if ($warehouseMappings->isEmpty())
            <x-empty-state icon="warehouse" :title="__('jtl_wawi::jtl_wawi.warehouses.empty')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('jtl_wawi::jtl_wawi.warehouses.jtl') }}</th>
                        <th>{{ __('jtl_wawi::jtl_wawi.warehouses.type') }}</th>
                        <th>{{ __('jtl_wawi::jtl_wawi.warehouses.flags') }}</th>
                        <th>{{ __('jtl_wawi::jtl_wawi.warehouses.local') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($warehouseMappings as $mapping)
                    <tr>
                        <td>
                            {{ $mapping->name }}
                            @if ($mapping->code)<span class="text-muted">({{ $mapping->code }})</span>@endif
                        </td>
                        <td class="text-sm text-muted">{{ $mapping->warehouse_type ?? '—' }}</td>
                        <td>
                            @unless ($mapping->jtl_is_active)<x-status-badge size="xs">{{ __('jtl_wawi::jtl_wawi.warehouses.inactive') }}</x-status-badge>@endunless
                            @if ($mapping->lock_for_shipment)<x-status-badge tone="warning" size="xs">{{ __('jtl_wawi::jtl_wawi.warehouses.lock_shipment') }}</x-status-badge>@endif
                            @if ($mapping->lock_for_availability)<x-status-badge tone="warning" size="xs">{{ __('jtl_wawi::jtl_wawi.warehouses.lock_availability') }}</x-status-badge>@endif
                        </td>
                        <td colspan="2">
                            <form method="POST" action="{{ route('admin.jtl.warehouses.map', $mapping) }}" class="flex items-center gap-2">
                                @csrf
                                <select name="warehouse" class="select select-bordered select-xs">
                                    <option value="">{{ __('jtl_wawi::jtl_wawi.warehouses.unmapped') }}</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->sqid }}" @selected($mapping->warehouse_id === $warehouse->id)>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                <x-button type="submit" tone="plain" size="xs">{{ __('jtl_wawi::jtl_wawi.action.map') }}</x-button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

    {{-- Bestandsführung (Moduswechsel) --}}
    @if ($canConfigureInventory)
        <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.jtl.mode.update') }}"
                data-confirm-dialog data-confirm-message="{{ __('jtl_wawi::jtl_wawi.confirm.mode_change') }}">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi::jtl_wawi.inventory.heading') }}</h2>
            <p class="text-sm text-muted">{{ __('jtl_wawi::jtl_wawi.inventory.explain') }}</p>

            <div class="flex flex-col gap-2">
                @foreach (\App\Enums\Inventory\InventoryMode::cases() as $mode)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="radio" name="inventory_mode" value="{{ $mode->value }}" class="radio radio-sm"
                               @checked($inventoryMode === $mode)>
                        <span class="label-text">{{ __('jtl_wawi::jtl_wawi.inventory.mode_' . $mode->value) }}</span>
                    </label>
                @endforeach
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <x-button type="submit">{{ __('jtl_wawi::jtl_wawi.action.change_mode') }}</x-button>
            </div>
        </x-card>
    @endif
</x-index-page>
@endsection
