{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('orgamax::orgamax.title'))
@section('nav-title', __('orgamax::orgamax.title'))

@section('content')
<x-index-page :subtitle="__('orgamax::orgamax.intro')">
    <x-slot:actions>
        <x-button :href="route('admin.integration.inbox', ['plugin' => 'orgamax'])" tone="ghost" size="sm">{{ __('orgamax::orgamax.to_inbox') }}@if ($openInboxCount > 0) ({{ $openInboxCount }})@endif</x-button>
        @if ($connection?->isActive())
            <form method="POST" action="{{ route('admin.orgamax.sync') }}">
                @csrf
                <x-button type="submit" tone="primary" size="sm">{{ __('orgamax::orgamax.action.sync_now') }}</x-button>
            </form>
        @endif
    </x-slot:actions>

    <x-validation-errors first />
    @if (session('orgamax_callback_url'))
        <div role="status" class="alert alert-info text-sm">
            <div>
                <strong>{{ __('orgamax::orgamax.connect.callback_url_label') }}</strong><br>
                <code class="break-all text-xs">{{ session('orgamax_callback_url') }}</code><br>
                {{ __('orgamax::orgamax.connect.callback_url_hint') }}
            </div>
        </div>
    @endif

    {{-- Plugin-Karte: Verbindung, Account, Scopes, Gesundheit --}}
    <x-card>
        <p class="text-xs text-muted">{{ __('orgamax::orgamax.erp_notice') }}</p>

        @if ($connection === null || $connection->status === \App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus::Disconnected || $connection->status === \App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus::Draft)
            {{-- Geführter Verbindungsdialog --}}
            <form method="POST" action="{{ route('admin.orgamax.connect') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                @csrf
                <label class="form-control">
                    <span class="label-text text-sm">{{ __('orgamax::orgamax.connect.mode') }}</span>
                    <select name="mode" class="select select-bordered select-sm">
                        <option value="private">{{ __('orgamax::orgamax.connect.mode_private') }}</option>
                        <option value="marketplace">{{ __('orgamax::orgamax.connect.mode_marketplace') }}</option>
                    </select>
                </label>
                <div></div>
                <label class="form-control">
                    <span class="label-text text-sm">{{ __('orgamax::orgamax.connect.api_key') }}</span>
                    <input type="password" name="api_key" autocomplete="off" class="input input-bordered input-sm">
                </label>
                <label class="form-control">
                    <span class="label-text text-sm">{{ __('orgamax::orgamax.connect.api_secret') }}</span>
                    <input type="password" name="api_secret" autocomplete="off" class="input input-bordered input-sm">
                </label>
                <div class="sm:col-span-2">
                    <x-button type="submit">{{ __('orgamax::orgamax.connect.start') }}</x-button>
                    <p class="mt-1 text-xs text-muted">{{ __('orgamax::orgamax.connect.start_hint') }}</p>
                </div>
            </form>
        @else
            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge :tone="$connection->isActive() ? 'success' : ($connection->status === \App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus::Blocked ? 'error' : 'warning')">
                    {{ $connection->status->label() }}
                </x-status-badge>
                <x-status-badge>{{ __('orgamax::orgamax.connect.mode') }}: {{ __('orgamax::orgamax.connect.mode_' . $connection->mode) }}</x-status-badge>
                @if ($connection->last_sync_at)
                    <span class="text-xs text-muted">{{ __('orgamax::orgamax.sync.last', ['at' => $connection->last_sync_at->fdatetime()]) }}</span>
                @endif
            </div>
            @if ($connection->blocked_reason)
                <p class="mt-1 text-sm text-error">{{ __('orgamax::orgamax.connect.blocked', ['reason' => $connection->blocked_reason]) }}</p>
            @endif

            @if ($connection->status === \App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus::PendingConfirmation)
                {{-- Ausdrückliche Kontobestätigung (Anti-Fremd-iid) --}}
                <div class="mt-3 rounded-box border border-info/40 bg-info/5 p-3 text-sm">
                    <strong>{{ __('orgamax::orgamax.connect.detected_account') }}:</strong>
                    {{ (string) ($connection->account_snapshot['name'] ?? $connection->account_snapshot['company'] ?? '—') }}
                    <form method="POST" action="{{ route('admin.orgamax.confirm') }}" class="mt-2">
                        @csrf
                        <x-button type="submit" tone="success">{{ __('orgamax::orgamax.connect.confirm_button') }}</x-button>
                    </form>
                </div>
            @endif

            <div class="mt-2 text-xs text-muted">
                {{ __('orgamax::orgamax.connect.scopes') }}: {{ implode(', ', (array) ($connection->granted_scopes ?? [])) ?: '—' }}
            </div>

            <form method="POST" action="{{ route('admin.orgamax.disconnect') }}" class="mt-3"
                  data-confirm-dialog data-confirm-message="{{ __('orgamax::orgamax.connect.disconnect_confirm') }}">
                @csrf
                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('orgamax::orgamax.connect.disconnect') }}</x-button>
            </form>
        @endif
    </x-card>

    @if ($connection !== null && $connection->status !== \App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus::Disconnected)
        {{-- Capability-Matrix / Datenführerschaft --}}
        <x-card>
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax::orgamax.capabilities.heading') }}</h2>
            <p class="mb-2 text-xs text-muted">{{ __('orgamax::orgamax.capabilities.hint') }}</p>
            <form method="POST" action="{{ route('admin.orgamax.capabilities') }}">
                @csrf
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('orgamax::orgamax.capabilities.capability') }}</th>
                            <th>{{ __('orgamax::orgamax.capabilities.enabled') }}</th>
                            <th>{{ __('orgamax::orgamax.capabilities.leader') }}</th>
                            <th>{{ __('orgamax::orgamax.capabilities.scopes') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach (\App\Plugins\OrgaMax\Models\OrgaMaxConnection::CAPABILITIES as $capability)
                        @php
                            $entry = (array) (($connection->capabilities ?? [])[$capability] ?? []);
                            $isExpense = $capability === 'expenses';
                            $expenseBlocked = $isExpense && ! $expenseContractConfirmed;
                        @endphp
                        <tr>
                            <td>
                                {{ __('orgamax::orgamax.capabilities.' . $capability) }}
                                @if ($expenseBlocked)
                                    <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('orgamax::orgamax.capabilities.expense_blocked') }}</x-status-badge>
                                @endif
                            </td>
                            <td>
                                <input type="hidden" name="capabilities[{{ $capability }}][enabled]" value="0">
                                <input type="checkbox" name="capabilities[{{ $capability }}][enabled]" value="1"
                                       class="checkbox checkbox-sm"
                                       @checked((bool) ($entry['enabled'] ?? false))
                                       @disabled($expenseBlocked)>
                            </td>
                            <td>
                                <select name="capabilities[{{ $capability }}][leader]" class="select select-bordered select-xs">
                                    @foreach (['manual_review', 'orgamax', 'workdiary'] as $leader)
                                        <option value="{{ $leader }}" @selected(($entry['leader'] ?? 'manual_review') === $leader)>
                                            {{ __('orgamax::orgamax.leader.' . $leader) }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-xs text-muted">{{ implode(', ', $requiredScopes[$capability] ?? []) }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <x-button type="submit" tone="outline" class="mt-2">{{ __('orgamax::orgamax.capabilities.save') }}</x-button>
            </form>
        </x-card>

        {{-- Übergebene Aufträge --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax::orgamax.orders.heading') }}</h2>
            @if ($orders->total() === 0)
                <x-empty-state icon="assignment" :title="__('orgamax::orgamax.orders.empty')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('orgamax::orgamax.orders.id') }}</th>
                            <th>{{ __('orgamax::orgamax.orders.marker') }}</th>
                            <th>{{ __('orgamax::orgamax.orders.synced') }}</th>
                            <th class="text-right">{{ __('orgamax::orgamax.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="font-mono text-xs">{{ $order->external_id }}</td>
                            <td class="font-mono text-xs">{{ (string) data_get($order->payload, 'marker', '—') }}</td>
                            <td class="text-xs text-muted">{{ $order->synced_at?->fdatetime() ?? '—' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.orgamax.invoices.convert') }}" class="inline"
                                      data-confirm-dialog data-confirm-message="{{ __('orgamax::orgamax.invoice.convert_confirm') }}">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $order->external_id }}">
                                    <x-button type="submit" tone="outline" size="xs">{{ __('orgamax::orgamax.invoice.convert') }}</x-button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                {{-- Inline, weil die stehende Fußzeile der Rechnungs-Projektion gehört. --}}
                <x-pagination :paginator="$orders" :framed="false" />
            @endif
        </x-card>

        {{-- Rechnungs-Projektion (Herkunft orgaMAX) --}}
        <x-card>
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax::orgamax.invoices.heading') }}</h2>
            <p class="mb-2 text-xs text-muted">{{ __('orgamax::orgamax.invoices.hint') }}</p>
            @if ($invoices->total() === 0)
                <x-empty-state icon="receipt_long" :title="__('orgamax::orgamax.invoices.empty')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('orgamax::orgamax.invoices.number') }}</th>
                            <th>{{ __('orgamax::orgamax.invoices.status') }}</th>
                            <th>{{ __('orgamax::orgamax.invoices.customer') }}</th>
                            <th class="text-right">{{ __('orgamax::orgamax.invoices.gross') }}</th>
                            <th>{{ __('orgamax::orgamax.invoices.synced') }}</th>
                            <th class="text-right">{{ __('orgamax::orgamax.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($invoices as $projection)
                        @php
                            $p = (array) $projection->payload;
                            $invoiceStatus = \App\Plugins\OrgaMax\Enums\OrgaMaxInvoiceStatus::fromStored(is_string($p['status'] ?? null) ? $p['status'] : null);
                        @endphp
                        <tr>
                            <td>{{ (string) ($p['number'] ?? '—') }}</td>
                            <td><x-status-badge :tone="$invoiceStatus->tone()">{{ $invoiceStatus->label() }}</x-status-badge></td>
                            <td class="text-sm">{{ (string) ($p['customer'] ?? '—') }}</td>
                            <td class="text-right tabular-nums">{{ $p['total_gross'] ?? '—' }}</td>
                            <td class="text-xs text-muted">{{ $projection->synced_at?->fdatetime() ?? '—' }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    <x-button :href="route('admin.orgamax.invoices.pdf', $projection->external_id)" tone="ghost" size="xs">PDF</x-button>
                                    <form method="POST" action="{{ route('admin.orgamax.invoices.lock', $projection->external_id) }}" class="inline"
                                          data-confirm-dialog data-confirm-message="{{ __('orgamax::orgamax.invoice.lock_confirm') }}">
                                        @csrf
                                        <x-button type="submit" tone="error" size="xs" class="btn-outline">{{ __('orgamax::orgamax.invoice.lock') }}</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                <x-pagination :paginator="$invoices" standing />
            @endif
        </x-card>

        {{-- Sync-Protokoll --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax::orgamax.sync.heading') }}</h2>
            @if ($connection->last_sync_counters)
                <ul class="text-sm">
                    @foreach ((array) $connection->last_sync_counters as $key => $value)
                        <li><span class="font-mono text-xs">{{ $key }}</span>: {{ $value }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-muted">{{ __('orgamax::orgamax.sync.never') }}</p>
            @endif
            @if ($connection->last_error)
                <p class="mt-1 text-sm text-error">{{ __('orgamax::orgamax.sync.error', ['error' => $connection->last_error]) }}</p>
            @endif
        </x-card>
    @endif
</x-index-page>
@endsection
