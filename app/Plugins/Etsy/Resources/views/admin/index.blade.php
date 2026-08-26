{{--
  Created on   : Tue Aug 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('etsy.title'))
@section('nav-title', __('etsy.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif

        {{-- Status-Karte --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('etsy.title') }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($openInbox > 0)
                        <span class="badge badge-warning badge-sm">{{ __('etsy.open_inbox', ['count' => $openInbox]) }}</span>
                    @endif
                    @if ($connection?->last_synced_at)
                        <span class="badge badge-ghost badge-sm">{{ __('etsy.last_sync', ['at' => $connection->last_synced_at->diffForHumans()]) }}</span>
                    @endif
                    @if ($connection?->isActive())
                        <form method="POST" action="{{ route('admin.etsy.sync') }}">
                            @csrf
                            <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('etsy.action.sync') }}</x-icon-btn>
                        </form>
                    @endif
                </div>
            </div>
            <p class="mb-2 text-sm text-muted">{{ __('etsy.intro') }}</p>

            {{-- Verbindung --}}
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @if ($connection?->isActive())
                    <span class="badge badge-success badge-sm">{{ __('etsy.connection.active', ['shop' => $connection->shop_name ?? ('#' . $connection->shop_id)]) }}</span>
                    <form method="POST" action="{{ route('admin.etsy.disconnect') }}" data-confirm="{{ __('etsy.connection.disconnect_confirm') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-xs">{{ __('etsy.connection.disconnect') }}</button>
                    </form>
                @elseif ($connection !== null && trim((string) $connection->access_token) !== '' && $connection->shop_id === null)
                    <span class="badge badge-warning badge-sm">{{ __('etsy.connection.shop_pending') }}</span>
                    @if ($connection->last_error === 'shop_already_bound')
                        <span class="text-error">{{ __('etsy.connection.shop_conflict') }}</span>
                    @endif
                @else
                    <span class="badge badge-ghost badge-sm">{{ __('etsy.connection.none') }}</span>
                @endif
                @if ($configured && ! $connection?->isActive())
                    <form method="POST" action="{{ route('admin.etsy.oauth.start') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-xs">{{ __('etsy.connection.connect') }}</button>
                    </form>
                @elseif (! $configured)
                    <span class="text-muted">{{ __('etsy.connection.not_configured') }}</span>
                @endif
            </div>

            {{-- Einrichtungshinweise: Redirect-URI (Seller-App) + Webhook-URL (Portal) --}}
            <div class="mt-3 space-y-1 text-xs text-muted">
                <div>{{ __('etsy.setup.callback') }} <code class="select-all">{{ $callbackUrl }}</code></div>
                @if ($webhookUrl !== null)
                    <div>{{ __('etsy.setup.webhook') }} <code class="select-all">{{ $webhookUrl }}</code></div>
                @endif
                {{-- Pflicht-Disclaimer (Etsy API Terms) — nicht übersetzen. --}}
                <div class="italic">The term “Etsy” is a trademark of Etsy, Inc. This application uses the Etsy API but is not endorsed or certified by Etsy, Inc.</div>
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('admin.etsy.index') }}" class="flex flex-wrap items-end gap-2">
            <label class="form-control">
                <span class="label-text text-xs">{{ __('etsy.field.status') }}</span>
                <select name="status" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('etsy.filter.all_statuses') }}</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn-sm">{{ __('etsy.filter.apply') }}</button>
        </form>

        {{-- Bestellspiegel --}}
        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('etsy.field.receipt') }}</th>
                        <th>{{ __('etsy.field.status') }}</th>
                        <th>{{ __('etsy.field.buyer') }}</th>
                        <th>{{ __('etsy.field.customer') }}</th>
                        <th class="text-right">{{ __('etsy.field.total') }}</th>
                        <th>{{ __('etsy.field.ordered_at') }}</th>
                        <th>{{ __('etsy.field.shipping') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td class="font-mono text-xs">{{ $receipt->receipt_id }}</td>
                            <td><span class="badge badge-ghost badge-sm">{{ $receipt->status ?? '—' }}</span></td>
                            <td>{{ data_get($receipt->buyer, 'name') ?? data_get($receipt->buyer, 'email') ?? '—' }}</td>
                            <td>
                                @if ($receipt->customer)
                                    {{ $receipt->customer->name }}
                                @elseif ($receipt->buyer_external_id !== null)
                                    <span class="badge badge-warning badge-sm">{{ __('etsy.status.open_assignment') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('etsy.status.guest') }}</span>
                                @endif
                            </td>
                            {{-- Anzeige-Makros statt Roh-Formatierung (Vollaudit 2026-07, N52). --}}
                            <td class="text-right font-mono text-xs">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($receipt->total_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $receipt->currency?->value }}</td>
                            <td class="text-xs">{{ $receipt->ordered_at?->fdatetime() ?? '—' }}</td>
                            <td>
                                @if ($receipt->was_shipped || $receipt->shipped_pushed_at !== null)
                                    <span class="badge badge-success badge-sm">{{ __('etsy.status.shipped') }}</span>
                                @else
                                    <details>
                                        <summary class="btn btn-ghost btn-xs">{{ __('etsy.action.ship') }}</summary>
                                        <form method="POST" action="{{ route('admin.etsy.receipts.ship', $receipt) }}" class="mt-1 flex flex-wrap items-end gap-1">
                                            @csrf
                                            <input type="text" name="tracking_code" placeholder="{{ __('etsy.field.tracking_code') }}" class="input input-xs input-bordered w-32" maxlength="100" />
                                            <input type="text" name="carrier_name" placeholder="{{ __('etsy.field.carrier') }}" class="input input-xs input-bordered w-24" maxlength="100" />
                                            <button type="submit" class="btn btn-primary btn-xs">{{ __('etsy.action.ship_submit') }}</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" icon="storefront" :title="__('etsy.empty')" compact />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$receipts" standing />

        {{-- Ledger-Summen (MVP-498) --}}
        @if ($ledgerSums->isNotEmpty())
            <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                <table class="table table-sm">
                    <caption class="p-2 text-left text-xs text-muted">{{ __('etsy.ledger.caption') }}</caption>
                    <thead>
                        <tr>
                            <th>{{ __('etsy.ledger.type') }}</th>
                            <th class="text-right">{{ __('etsy.ledger.amount') }}</th>
                            <th class="text-right">{{ __('etsy.ledger.entries') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledgerSums as $sum)
                            <tr>
                                <td>{{ $sum->ledger_type ?? '—' }}</td>
                                <td class="text-right font-mono text-xs">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((int) $sum->amount_sum) / 100, 2, withThousandsSeparator: true) }} {{ $sum->currency }}</td>
                                <td class="text-right font-mono text-xs">{{ $sum->entries }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
