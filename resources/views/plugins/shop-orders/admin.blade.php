{{--
  Created on   : Sat Oct 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : admin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verwaltungsseite der Shop-Importe (WooCommerce, Shopware, PrestaShop;
  Feature 175). Variablen aus ShopOrderAdminController::index(): $provider,
  $pluginId, $routePrefix, $configured, $connection, $connected, $orders,
  $search, $status, $openInbox, $windowDays und $urls (Bestell-ID → Link in
  die Shop-Verwaltung oder null).
--}}
@extends('layouts.app')
@section('title', $provider . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $provider)
@include('partials.page-fill')

@section('content')
@php
    $r = ['provider' => $provider];
    $ready = $connected && $connection->isReady();
    $confirmed = $connection?->isImportConfirmed() === true;
@endphp
<x-index-page overflow="clip"
    :subtitle="__('integration.shop_orders.page.subtitle', $r)"
    :badge="$connected ? __('integration.shop_orders.connection.connected') : __('integration.shop_orders.connection.not_connected')"
    :badge-tone="$ready && $confirmed ? 'success' : ($connected ? 'warning' : 'ghost')">

    <x-slot:actions>
        @if ($ready && ! $confirmed)
            <x-button :href="route($routePrefix . '.preview')" data-entry-modal-trigger icon="preview" placement="bar">{{ __('integration.shop_orders.action.preview') }}</x-button>
        @elseif ($ready)
            <x-action-form :action="route($routePrefix . '.sync')">
                <x-button type="submit" icon="sync" placement="bar">{{ __('integration.shop_orders.connection.sync_now') }}</x-button>
            </x-action-form>
            <x-button :href="route($routePrefix . '.preview')" data-entry-modal-trigger tone="ghost" icon="preview" placement="menu">{{ __('integration.shop_orders.action.preview') }}</x-button>
        @endif
        @if ($connected)
            <x-button :href="route('admin.integration.inbox', ['plugin' => $pluginId])" tone="ghost" icon="inbox" placement="menu">{{ __('integration.shop_orders.connection.open_inbox', ['count' => $openInbox]) }}</x-button>
            <x-action-form :action="route($routePrefix . '.verify')">
                <x-button type="submit" tone="ghost" icon="link" placement="menu">{{ __('integration.shop_orders.connection.verify') }}</x-button>
            </x-action-form>
            <x-action-form :action="route($routePrefix . '.disconnect')" :confirm="__('integration.shop_orders.connection.confirm_disconnect', $r)" confirm-tone="error">
                <x-button type="submit" tone="ghost" icon="link_off" placement="danger">{{ __('integration.shop_orders.connection.disconnect') }}</x-button>
            </x-action-form>
        @elseif ($configured)
            <x-action-form :action="route($routePrefix . '.verify')">
                <x-button type="submit" icon="link" placement="bar">{{ __('integration.shop_orders.connection.verify') }}</x-button>
            </x-action-form>
        @endif
        <x-button :href="route('admin.plugins.edit', $pluginId)" data-entry-modal-trigger tone="ghost" icon="key" placement="menu">{{ __('integration.shop_orders.connection.settings') }}</x-button>
    </x-slot:actions>

    @unless ($configured)
        <div role="alert" class="alert alert-warning text-sm">
            <x-icon name="key_off" />
            <span>{{ __('integration.shop_orders.connection.not_configured', $r) }}</span>
        </div>
    @endunless

    <x-card :title="__('integration.shop_orders.connection.title')">
        @if (! $connected)
            <div role="note" class="alert text-sm">
                <x-icon name="privacy_tip" />
                <span>{{ __('integration.shop_orders.connection.privacy_note', $r) }}</span>
            </div>
            <x-empty-state icon="storefront" :title="__('integration.shop_orders.connection.none', $r)" :message="__('integration.shop_orders.connection.none_hint')" compact />
        @else
            <x-detail-grid class="sm:grid-cols-2">
                <x-detail-grid.row :label="__('integration.shop_orders.connection.shop')">{{ $connection->source_name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('integration.shop_orders.connection.address')">{{ $connection->source_url ?? '—' }}</x-detail-grid.row>
                @if ($connection->account_name)
                    <x-detail-grid.row :label="__('integration.shop_orders.connection.account')">{{ $connection->account_name }}</x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('integration.shop_orders.connection.connected_at')">{{ $connection->connected_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('integration.shop_orders.connection.import')">
                    {{ $confirmed ? __('integration.shop_orders.connection.import_confirmed', ['date' => $connection->import_confirmed_at->fdatetime()]) : __('integration.shop_orders.connection.import_pending') }}
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('integration.shop_orders.connection.window')">{{ __('integration.shop_orders.connection.window_value', ['days' => $windowDays]) }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('integration.shop_orders.connection.last_sync')">
                    {{ $connection->last_synced_at?->fdatetime() ?? '—' }}
                    @if (is_array($connection->last_sync_counters))
                        <div class="text-xs text-muted">{{ __('integration.shop_orders.connection.counters', array_map('intval', $connection->last_sync_counters + ['imported' => 0, 'updated' => 0, 'linked' => 0, 'staged' => 0, 'failed' => 0, 'deleted' => 0])) }}</div>
                    @endif
                    @if ($connection->is_last_run_complete === false)
                        <x-status-badge tone="warning" size="xs">{{ __('integration.shop_orders.connection.incomplete') }}</x-status-badge>
                    @endif
                </x-detail-grid.row>
                @if ($connection->last_error)
                    <x-detail-grid.row :label="__('integration.shop_orders.connection.last_error')" full class="text-error">{{ $connection->last_error }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
        @endif
    </x-card>

    <x-filter-bar :action="route($routePrefix . '.index')" :reset="$search !== null || $status !== null ? route($routePrefix . '.index') : null">
        <x-filter-field :label="__('integration.shop_orders.orders.search')" for="shop-q">
            <input id="shop-q" type="search" name="q" value="{{ $search }}" maxlength="100"
                   class="input input-sm input-bordered w-56 shrink-0" placeholder="{{ __('integration.shop_orders.orders.search_placeholder') }}">
        </x-filter-field>
        <x-filter-field :label="__('integration.shop_orders.orders.col.status')" for="shop-status">
            <select id="shop-status" name="status" class="select select-sm select-bordered shrink-0" data-autosubmit>
                <option value="">{{ __('integration.shop_orders.orders.all_statuses') }}</option>
                @foreach (\App\Enums\Integration\ShopOrderStatus::cases() as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('integration.shop_orders.orders.col.order') }}</th>
                <th>{{ __('integration.shop_orders.orders.col.date') }}</th>
                <th>{{ __('integration.shop_orders.orders.col.buyer') }}</th>
                <th>{{ __('integration.shop_orders.orders.col.customer') }}</th>
                <th>{{ __('integration.shop_orders.orders.col.status') }}</th>
                <th class="text-right">{{ __('integration.shop_orders.orders.col.total') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($orders as $order)
            <tr>
                <td class="whitespace-nowrap">
                    @if (($urls[$order->id] ?? null) !== null)
                        <a href="{{ $urls[$order->id] }}" target="_blank" rel="noopener noreferrer" class="link link-hover inline-flex items-center gap-1 font-mono text-sm"
                           title="{{ __('integration.shop_orders.orders.open_in_shop', $r) }}">{{ $order->displayNumber() }}<x-icon name="open_in_new" class="text-sm" /></a>
                    @else
                        <span class="font-mono text-sm">{{ $order->displayNumber() }}</span>
                    @endif
                </td>
                <td class="text-sm whitespace-nowrap">{{ $order->ordered_at?->fdatetime() ?? '—' }}</td>
                <td class="text-sm">{{ $order->buyerLabel() ?? '—' }}</td>
                <td class="text-sm">
                    @if ($order->customer)
                        <a href="{{ route('customers.show', $order->customer) }}" class="link link-hover">{{ $order->customer->name }}</a>
                    @else
                        <x-status-badge tone="warning" size="xs">{{ __('integration.shop_orders.orders.unassigned') }}</x-status-badge>
                    @endif
                </td>
                <td class="whitespace-nowrap">
                    <x-status-badge :tone="$order->status->tone()" size="xs">{{ $order->status->label() }}</x-status-badge>
                    @if ($order->remote_deleted_at)
                        <x-status-badge tone="ghost" size="xs" :title="$order->remote_deleted_at->fdatetime()">{{ __('integration.shop_orders.orders.deleted') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right font-mono text-sm whitespace-nowrap">
                    {{ $order->gross_amount?->format() ?? '—' }}
                    @if ($order->refunded_amount?->isPositive())
                        <div class="text-xs text-muted">{{ __('integration.shop_orders.orders.refunded', ['amount' => $order->refunded_amount->format()]) }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" icon="storefront" :title="__('integration.shop_orders.orders.empty')" :message="__('integration.shop_orders.orders.empty_hint')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$orders" standing />
</x-index-page>
@endsection
