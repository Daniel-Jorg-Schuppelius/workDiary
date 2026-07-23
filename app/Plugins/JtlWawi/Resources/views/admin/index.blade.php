@extends('layouts.app')
@section('title', __('jtl_wawi.title'))
@section('nav-title', __('jtl_wawi.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Status-Karte --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('jtl_wawi.title') }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($connection)
                        <span class="badge badge-ghost badge-sm">{{ __('jtl_wawi.mode.' . $connection->mode) }}</span>
                        @if ($connection->status === \App\Models\JtlConnection::STATUS_ACTIVE)
                            <span class="badge badge-success badge-sm">{{ __('jtl_wawi.status.active') }}</span>
                        @elseif ($connection->status === \App\Models\JtlConnection::STATUS_PENDING_REGISTRATION)
                            <span class="badge badge-warning badge-sm">{{ __('jtl_wawi.status.pending_registration') }}</span>
                        @elseif ($connection->status === \App\Models\JtlConnection::STATUS_BLOCKED)
                            <span class="badge badge-error badge-sm">{{ __('jtl_wawi.status.blocked') }}: {{ $connection->blocked_reason }}</span>
                        @else
                            <span class="badge badge-ghost badge-sm">{{ __('jtl_wawi.status.' . $connection->status) }}</span>
                        @endif
                    @endif
                </div>
            </div>
            <p class="mb-3 text-sm text-base-content/60">{{ __('jtl_wawi.intro') }}</p>
            <p class="mb-3 text-xs text-warning">{{ __('jtl_wawi.beta_notice') }}</p>

            @if ($connection)
                <dl class="mb-3 grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                    @if ($connection->detected_version)
                        <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('jtl_wawi.field.detected_version') }}</dt><dd>{{ $connection->detected_version }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('jtl_wawi.field.api_version') }}</dt><dd>{{ $connection->api_version }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('jtl_wawi.field.last_sync') }}</dt><dd>{{ $connection->last_sync_at?->diffForHumans() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('jtl_wawi.stats.linked_articles') }}</dt><dd>{{ $linkedArticles }}</dd></div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-base-content/60">{{ __('jtl_wawi.stats.open_inbox') }}</dt>
                        <dd>
                            @if ($openInbox > 0)
                                <a href="{{ route('admin.integration.inbox', ['plugin' => \App\Plugins\JtlWawi\JtlWawiPlugin::ID]) }}" class="link">{{ $openInbox }}</a>
                            @else
                                0
                            @endif
                        </dd>
                    </div>
                    @if ($connection->last_error)
                        <div class="flex justify-between gap-4 md:col-span-2"><dt class="text-base-content/60">{{ __('jtl_wawi.field.last_error') }}</dt><dd class="text-error">{{ $connection->last_error }}</dd></div>
                    @endif
                </dl>

                @if ($scopeCheck && ! $scopeCheck['ok'] && ! $scopeCheck['unknown'])
                    <div class="alert alert-warning text-sm">
                        {{ __('jtl_wawi.scopes.missing', ['scopes' => implode(', ', $scopeCheck['missing_read'])]) }}
                    </div>
                @endif
                @if ($scopeCheck && ($scopeCheck['missing_write'] ?? []) !== [] && ! $scopeCheck['unknown'])
                    <p class="text-xs text-base-content/60">{{ __('jtl_wawi.scopes.missing_write', ['scopes' => implode(', ', $scopeCheck['missing_write'])]) }}</p>
                @endif

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($connection->isActive())
                        <form method="POST" action="{{ route('admin.jtl.sync') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('jtl_wawi.action.sync_now') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.jtl.connection.disconnect') }}"
                          data-confirm-dialog
                          data-confirm-message="{{ __('jtl_wawi.confirm.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('jtl_wawi.action.disconnect') }}</button>
                    </form>
                </div>

                @if (is_array($connection->last_sync_counters) && $connection->last_sync_counters !== [])
                    <div class="mt-3 overflow-x-auto">
                        <table class="table table-xs">
                            <thead><tr><th>{{ __('jtl_wawi.sync.section') }}</th><th>{{ __('jtl_wawi.sync.counters') }}</th></tr></thead>
                            <tbody>
                                @foreach ($connection->last_sync_counters as $section => $counters)
                                    <tr>
                                        <td>{{ __('jtl_wawi.sync.' . $section) }}</td>
                                        <td class="font-mono text-xs">
                                            @foreach ((array) $counters as $key => $value)
                                                <span class="mr-2">{{ $key }}: {{ is_bool($value) ? ($value ? '✓' : '—') : $value }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>

        {{-- OnPremise-Registrierung --}}
        @if ($connection && $connection->isOnPremise() && ! $connection->isActive())
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi.registration.heading') }}</h2>
                @if ($connection->status === \App\Models\JtlConnection::STATUS_PENDING_REGISTRATION)
                    <div class="alert alert-info text-sm">{{ __('jtl_wawi.registration.waiting') }}</div>
                    <form method="POST" action="{{ route('admin.jtl.connection.check') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('jtl_wawi.action.check_registration') }}</button>
                    </form>
                @else
                    <p class="text-sm text-base-content/60">{{ __('jtl_wawi.registration.explain') }}</p>
                    <form method="POST" action="{{ route('admin.jtl.connection.register') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('jtl_wawi.action.start_registration') }}</button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Verbindung --}}
        <form method="POST" action="{{ route('admin.jtl.connection.store') }}"
              x-data="{ mode: @js(old('mode', $connection->mode ?? \App\Models\JtlConnection::MODE_ON_PREMISE)) }"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi.connection.heading') }}</h2>

            <div class="flex flex-wrap gap-4">
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="radio" name="mode" value="on_premise" class="radio radio-sm" x-model="mode">
                    <span class="label-text">{{ __('jtl_wawi.mode.on_premise') }}</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="radio" name="mode" value="cloud" class="radio radio-sm" x-model="mode">
                    <span class="label-text">{{ __('jtl_wawi.mode.cloud') }}</span>
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <template x-if="mode === 'on_premise'">
                    <label class="form-control md:col-span-2">
                        <span class="label-text">{{ __('jtl_wawi.field.base_url') }}</span>
                        <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                               placeholder="https://wawi.example.local:5883/api/eazybusiness" class="input input-bordered input-sm">
                        <span class="label-text-alt text-base-content/50">{{ __('jtl_wawi.field.base_url_help') }}</span>
                    </label>
                </template>

                <label class="form-control">
                    <span class="label-text">{{ __('jtl_wawi.field.api_version') }}</span>
                    <select name="api_version" class="select select-bordered select-sm">
                        @foreach (['2.0', '2.1'] as $version)
                            <option value="{{ $version }}" @selected(old('api_version', $connection->api_version ?? '2.0') === $version)>{{ $version }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('jtl_wawi.field.company_id') }}</span>
                    <input type="text" name="company_id" value="{{ old('company_id', $connection->company_id ?? '') }}"
                           class="input input-bordered input-sm" autocomplete="off">
                    <span class="label-text-alt text-base-content/50">{{ __('jtl_wawi.field.company_id_help') }}</span>
                </label>

                <template x-if="mode === 'on_premise'">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="allow_private_network" value="0">
                        <input type="checkbox" name="allow_private_network" value="1" class="toggle toggle-sm toggle-warning"
                               @checked(old('allow_private_network', $connection->allow_private_network ?? false))>
                        <span class="label-text">{{ __('jtl_wawi.field.allow_private_network') }}</span>
                    </label>
                </template>

                <template x-if="mode === 'cloud'">
                    <label class="form-control">
                        <span class="label-text">{{ __('jtl_wawi.field.tenant_id') }}</span>
                        <input type="text" name="tenant_id" value="{{ old('tenant_id', $connection->tenant_id ?? '') }}"
                               class="input input-bordered input-sm" autocomplete="off">
                    </label>
                </template>
                <template x-if="mode === 'cloud'">
                    <label class="form-control">
                        <span class="label-text">{{ __('jtl_wawi.field.client_id') }}</span>
                        <input type="password" name="client_id" autocomplete="new-password"
                               placeholder="{{ $connection && $connection->hasCredentials() ? __('jtl_wawi.field.secret_keep') : '' }}"
                               class="input input-bordered input-sm">
                    </label>
                </template>
                <template x-if="mode === 'cloud'">
                    <label class="form-control">
                        <span class="label-text">{{ __('jtl_wawi.field.client_secret') }}</span>
                        <input type="password" name="client_secret" autocomplete="new-password"
                               placeholder="{{ $connection && $connection->hasCredentials() ? __('jtl_wawi.field.secret_keep') : '' }}"
                               class="input input-bordered input-sm">
                    </label>
                </template>
            </div>

            <template x-if="mode === 'on_premise'">
                <p class="text-xs text-base-content/50">{{ __('jtl_wawi.field.allow_private_network_help') }}</p>
            </template>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('jtl_wawi.action.save') }}</button>
            </div>
        </form>

        {{-- Lager-Zuordnung --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi.warehouses.heading') }}</h2>
            @if ($warehouseMappings->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('jtl_wawi.warehouses.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('jtl_wawi.warehouses.jtl') }}</th>
                                <th>{{ __('jtl_wawi.warehouses.type') }}</th>
                                <th>{{ __('jtl_wawi.warehouses.flags') }}</th>
                                <th>{{ __('jtl_wawi.warehouses.local') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($warehouseMappings as $mapping)
                                <tr>
                                    <td>
                                        {{ $mapping->name }}
                                        @if ($mapping->code)<span class="text-base-content/50">({{ $mapping->code }})</span>@endif
                                    </td>
                                    <td class="text-sm text-base-content/60">{{ $mapping->warehouse_type ?? '—' }}</td>
                                    <td>
                                        @unless ($mapping->jtl_is_active)<span class="badge badge-ghost badge-xs">{{ __('jtl_wawi.warehouses.inactive') }}</span>@endunless
                                        @if ($mapping->lock_for_shipment)<span class="badge badge-warning badge-xs">{{ __('jtl_wawi.warehouses.lock_shipment') }}</span>@endif
                                        @if ($mapping->lock_for_availability)<span class="badge badge-warning badge-xs">{{ __('jtl_wawi.warehouses.lock_availability') }}</span>@endif
                                    </td>
                                    <td colspan="2">
                                        <form method="POST" action="{{ route('admin.jtl.warehouses.map', $mapping) }}" class="flex items-center gap-2">
                                            @csrf
                                            <select name="warehouse" class="select select-bordered select-xs">
                                                <option value="">{{ __('jtl_wawi.warehouses.unmapped') }}</option>
                                                @foreach ($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->sqid }}" @selected($mapping->warehouse_id === $warehouse->id)>{{ $warehouse->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-xs">{{ __('jtl_wawi.action.map') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Bestandsführung (Moduswechsel) --}}
        @if ($canConfigureInventory)
            <form method="POST" action="{{ route('admin.jtl.mode.update') }}"
                  data-confirm-dialog
                  data-confirm-message="{{ __('jtl_wawi.confirm.mode_change') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                @csrf
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('jtl_wawi.inventory.heading') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('jtl_wawi.inventory.explain') }}</p>

                <div class="flex flex-col gap-2">
                    @foreach (\App\Enums\Inventory\InventoryMode::cases() as $mode)
                        <label class="label cursor-pointer justify-start gap-2">
                            <input type="radio" name="inventory_mode" value="{{ $mode->value }}" class="radio radio-sm"
                                   @checked($inventoryMode === $mode)>
                            <span class="label-text">{{ __('jtl_wawi.inventory.mode_' . $mode->value) }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('jtl_wawi.action.change_mode') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-page-shell>
@endsection
