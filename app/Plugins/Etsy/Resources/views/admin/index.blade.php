{{--
  Created on   : Tue Aug 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('etsy::etsy.title'))
@section('nav-title', __('etsy::etsy.title'))

@section('content')
<x-index-page :subtitle="__('etsy::etsy.intro')">
    <x-slot:badges>
        @if ($openInbox > 0)
            <x-status-badge tone="warning">{{ __('etsy::etsy.open_inbox', ['count' => $openInbox]) }}</x-status-badge>
        @endif
        @if ($connection?->last_synced_at)
            <x-status-badge>{{ __('etsy::etsy.last_sync', ['at' => $connection->last_synced_at->diffForHumans()]) }}</x-status-badge>
        @endif
    </x-slot:badges>
    <x-slot:actions>
        @if ($connection?->isActive())
            <form method="POST" action="{{ route('admin.etsy.sync') }}">
                @csrf
                <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('etsy::etsy.action.sync') }}</x-icon-btn>
            </form>
        @endif
    </x-slot:actions>

    <x-card>
        {{-- Verbindung --}}
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($connection?->isActive())
                <x-status-badge tone="success">{{ __('etsy::etsy.connection.active', ['shop' => $connection->shop_name ?? ('#' . $connection->shop_id)]) }}</x-status-badge>
                <form method="POST" action="{{ route('admin.etsy.disconnect') }}" data-confirm-dialog data-confirm-message="{{ __('etsy::etsy.connection.disconnect_confirm') }}" data-confirm-tone="error">
                    @csrf
                    <x-button type="submit" tone="ghost" size="xs">{{ __('etsy::etsy.connection.disconnect') }}</x-button>
                </form>
            @elseif ($connection !== null && trim((string) $connection->access_token) !== '' && $connection->shop_id === null)
                <x-status-badge tone="warning">{{ __('etsy::etsy.connection.shop_pending') }}</x-status-badge>
                @if ($connection->last_error === 'shop_already_bound')
                    <span class="text-error">{{ __('etsy::etsy.connection.shop_conflict') }}</span>
                @endif
            @else
                <x-status-badge>{{ __('etsy::etsy.connection.none') }}</x-status-badge>
            @endif
            @if ($configured && ! $connection?->isActive())
                <form method="POST" action="{{ route('admin.etsy.oauth.start') }}">
                    @csrf
                    <x-button type="submit" size="xs">{{ __('etsy::etsy.connection.connect') }}</x-button>
                </form>
            @elseif (! $configured)
                <span class="text-muted">{{ __('etsy::etsy.connection.not_configured') }}</span>
            @endif
        </div>

        {{-- Einrichtungshinweise: Redirect-URI (Seller-App) + Webhook-URL (Portal) --}}
        <div class="mt-3 space-y-1 text-xs text-muted">
            <div>{{ __('etsy::etsy.setup.callback') }} <code class="select-all">{{ $callbackUrl }}</code></div>
            @if ($webhookUrl !== null)
                <div>{{ __('etsy::etsy.setup.webhook') }} <code class="select-all">{{ $webhookUrl }}</code></div>
            @endif
            {{-- Pflicht-Disclaimer (Etsy API Terms) — nicht übersetzen. --}}
            <div class="italic">The term “Etsy” is a trademark of Etsy, Inc. This application uses the Etsy API but is not endorsed or certified by Etsy, Inc.</div>
        </div>
    </x-card>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.etsy.index') }}" class="flex flex-wrap items-end gap-2">
        <label class="form-control">
            <span class="label-text text-xs">{{ __('etsy::etsy.field.status') }}</span>
            <select name="status" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('etsy::etsy.filter.all_statuses') }}</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <x-button type="submit" tone="plain">{{ __('etsy::etsy.filter.apply') }}</x-button>
    </form>

    {{-- Bestellspiegel --}}
    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('etsy::etsy.field.receipt') }}</th>
                <th>{{ __('etsy::etsy.field.status') }}</th>
                <th>{{ __('etsy::etsy.field.buyer') }}</th>
                <th>{{ __('etsy::etsy.field.customer') }}</th>
                <th class="text-right">{{ __('etsy::etsy.field.total') }}</th>
                <th>{{ __('etsy::etsy.field.ordered_at') }}</th>
                <th>{{ __('etsy::etsy.field.shipping') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($receipts as $receipt)
            <tr>
                <td class="font-mono text-xs">{{ $receipt->receipt_id }}</td>
                <td><x-status-badge>{{ $receipt->status !== null ? \App\Support\Trans::or('values.' . $receipt->status, $receipt->status) : '—' }}</x-status-badge></td>
                <td>{{ data_get($receipt->buyer, 'name') ?? data_get($receipt->buyer, 'email') ?? '—' }}</td>
                <td>
                    @if ($receipt->customer)
                        {{ $receipt->customer->name }}
                    @elseif ($receipt->buyer_external_id !== null)
                        <x-status-badge tone="warning">{{ __('etsy::etsy.status.open_assignment') }}</x-status-badge>
                    @else
                        <x-status-badge>{{ __('etsy::etsy.status.guest') }}</x-status-badge>
                    @endif
                </td>
                {{-- Anzeige-Makros statt Roh-Formatierung (Vollaudit 2026-07, N52). --}}
                <td class="text-right font-mono text-xs">{{ $receipt->total_gross?->format(withSymbol: false) ?? '0,00' }} {{ $receipt->currency?->value }}</td>
                <td class="text-xs">{{ $receipt->ordered_at?->fdatetime() ?? '—' }}</td>
                <td>
                    @if ($receipt->was_shipped || $receipt->shipped_pushed_at !== null)
                        <x-status-badge tone="success">{{ __('etsy::etsy.status.shipped') }}</x-status-badge>
                    @else
                        <details>
                            <summary class="btn btn-ghost btn-xs">{{ __('etsy::etsy.action.ship') }}</summary>
                            <form method="POST" action="{{ route('admin.etsy.receipts.ship', $receipt) }}" class="mt-1 flex flex-wrap items-end gap-1">
                                @csrf
                                <input aria-label="{{ __('etsy::etsy.field.tracking_code') }}" type="text" name="tracking_code" placeholder="{{ __('etsy::etsy.field.tracking_code') }}" class="input input-xs input-bordered w-32" maxlength="100" />
                                <input aria-label="{{ __('etsy::etsy.field.carrier') }}" type="text" name="carrier_name" placeholder="{{ __('etsy::etsy.field.carrier') }}" class="input input-xs input-bordered w-24" maxlength="100" />
                                <x-button type="submit" size="xs">{{ __('etsy::etsy.action.ship_submit') }}</x-button>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" icon="storefront" :title="__('etsy::etsy.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$receipts" standing />

    {{-- Ledger-Summen (MVP-498) --}}
    @if ($ledgerSums->isNotEmpty())
        <p class="mb-1 text-xs text-muted">{{ __('etsy::etsy.ledger.caption') }}</p>
        <x-table :caption="__('etsy::etsy.ledger.caption')">
            <x-slot:head>
                <tr>
                    <th>{{ __('etsy::etsy.ledger.type') }}</th>
                    <th class="text-right">{{ __('etsy::etsy.ledger.amount') }}</th>
                    <th class="text-right">{{ __('etsy::etsy.ledger.entries') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($ledgerSums as $sum)
                <tr>
                    <td>{{ $sum->ledger_type ?? '—' }}</td>
                    <td class="text-right font-mono text-xs">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((int) $sum->amount_sum) / 100, 2, withThousandsSeparator: true) }} {{ $sum->currency }}</td>
                    <td class="text-right font-mono text-xs">{{ $sum->entries }}</td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
