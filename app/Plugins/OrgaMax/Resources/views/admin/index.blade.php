@extends('layouts.app')
@section('title', __('orgamax.title'))
@section('nav-title', __('orgamax.title'))

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
        @if (session('orgamax_callback_url'))
            <div class="alert alert-info text-sm">
                <div>
                    <strong>{{ __('orgamax.connect.callback_url_label') }}</strong><br>
                    <code class="break-all text-xs">{{ session('orgamax_callback_url') }}</code><br>
                    {{ __('orgamax.connect.callback_url_hint') }}
                </div>
            </div>
        @endif

        {{-- Plugin-Karte: Verbindung, Account, Scopes, Gesundheit --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('orgamax.title') }}</h1>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.integration.inbox', ['plugin' => 'orgamax']) }}" class="btn btn-sm btn-ghost">
                        {{ __('orgamax.to_inbox') }}
                        @if ($openInboxCount > 0)
                            <span class="badge badge-sm badge-warning ml-1">{{ $openInboxCount }}</span>
                        @endif
                    </a>
                    @if ($connection?->isActive())
                        <form method="POST" action="{{ route('admin.orgamax.sync') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('orgamax.action.sync_now') }}</button>
                        </form>
                    @endif
                </div>
            </div>
            <p class="text-sm text-base-content/60">{{ __('orgamax.intro') }}</p>
            <p class="mt-1 text-xs text-base-content/50">{{ __('orgamax.erp_notice') }}</p>

            @if ($connection === null || $connection->status === \App\Models\OrgaMaxConnection::STATUS_DISCONNECTED || $connection->status === \App\Models\OrgaMaxConnection::STATUS_DRAFT)
                {{-- Geführter Verbindungsdialog --}}
                <form method="POST" action="{{ route('admin.orgamax.connect') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <label class="form-control">
                        <span class="label-text text-sm">{{ __('orgamax.connect.mode') }}</span>
                        <select name="mode" class="select select-bordered select-sm">
                            <option value="private">{{ __('orgamax.connect.mode_private') }}</option>
                            <option value="marketplace">{{ __('orgamax.connect.mode_marketplace') }}</option>
                        </select>
                    </label>
                    <div></div>
                    <label class="form-control">
                        <span class="label-text text-sm">{{ __('orgamax.connect.api_key') }}</span>
                        <input type="password" name="api_key" autocomplete="off" class="input input-bordered input-sm">
                    </label>
                    <label class="form-control">
                        <span class="label-text text-sm">{{ __('orgamax.connect.api_secret') }}</span>
                        <input type="password" name="api_secret" autocomplete="off" class="input input-bordered input-sm">
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('orgamax.connect.start') }}</button>
                        <p class="mt-1 text-xs text-base-content/50">{{ __('orgamax.connect.start_hint') }}</p>
                    </div>
                </form>
            @else
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge badge-sm {{ $connection->isActive() ? 'badge-success' : ($connection->status === \App\Models\OrgaMaxConnection::STATUS_BLOCKED ? 'badge-error' : 'badge-warning') }}">
                        {{ __('orgamax.status.' . $connection->status) }}
                    </span>
                    <span class="badge badge-ghost badge-sm">{{ __('orgamax.connect.mode') }}: {{ __('orgamax.connect.mode_' . $connection->mode) }}</span>
                    @if ($connection->last_sync_at)
                        <span class="text-xs text-base-content/50">{{ __('orgamax.sync.last', ['at' => $connection->last_sync_at->fdatetime()]) }}</span>
                    @endif
                </div>
                @if ($connection->blocked_reason)
                    <p class="mt-1 text-sm text-error">{{ __('orgamax.connect.blocked', ['reason' => $connection->blocked_reason]) }}</p>
                @endif

                @if ($connection->status === \App\Models\OrgaMaxConnection::STATUS_PENDING_CONFIRMATION)
                    {{-- Ausdrückliche Kontobestätigung (Anti-Fremd-iid) --}}
                    <div class="mt-3 rounded-box border border-info/40 bg-info/5 p-3 text-sm">
                        <strong>{{ __('orgamax.connect.detected_account') }}:</strong>
                        {{ (string) ($connection->account_snapshot['name'] ?? $connection->account_snapshot['company'] ?? '—') }}
                        <form method="POST" action="{{ route('admin.orgamax.confirm') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">{{ __('orgamax.connect.confirm_button') }}</button>
                        </form>
                    </div>
                @endif

                <div class="mt-2 text-xs text-base-content/50">
                    {{ __('orgamax.connect.scopes') }}: {{ implode(', ', (array) ($connection->granted_scopes ?? [])) ?: '—' }}
                </div>

                <form method="POST" action="{{ route('admin.orgamax.disconnect') }}" class="mt-3"
                      data-confirm-dialog data-confirm="{{ __('orgamax.connect.disconnect_confirm') }}">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('orgamax.connect.disconnect') }}</button>
                </form>
            @endif
        </div>

        @if ($connection !== null && $connection->status !== \App\Models\OrgaMaxConnection::STATUS_DISCONNECTED)
            {{-- Capability-Matrix / Datenführerschaft --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax.capabilities.heading') }}</h2>
                <p class="mb-2 text-xs text-base-content/50">{{ __('orgamax.capabilities.hint') }}</p>
                <form method="POST" action="{{ route('admin.orgamax.capabilities') }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('orgamax.capabilities.capability') }}</th>
                                    <th>{{ __('orgamax.capabilities.enabled') }}</th>
                                    <th>{{ __('orgamax.capabilities.leader') }}</th>
                                    <th>{{ __('orgamax.capabilities.scopes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (\App\Models\OrgaMaxConnection::CAPABILITIES as $capability)
                                    @php
                                        $entry = (array) (($connection->capabilities ?? [])[$capability] ?? []);
                                        $isExpense = $capability === 'expenses';
                                        $expenseBlocked = $isExpense && ! $expenseContractConfirmed;
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ __('orgamax.capabilities.' . $capability) }}
                                            @if ($expenseBlocked)
                                                <span class="badge badge-warning badge-xs ml-1">{{ __('orgamax.capabilities.expense_blocked') }}</span>
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
                                                        {{ __('orgamax.leader.' . $leader) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-xs text-base-content/50">{{ implode(', ', $requiredScopes[$capability] ?? []) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline mt-2">{{ __('orgamax.capabilities.save') }}</button>
                </form>
            </div>

            {{-- Übergebene Aufträge --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax.orders.heading') }}</h2>
                @if ($orders->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('orgamax.orders.empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('orgamax.orders.id') }}</th>
                                    <th>{{ __('orgamax.orders.marker') }}</th>
                                    <th>{{ __('orgamax.orders.synced') }}</th>
                                    <th class="text-right">{{ __('orgamax.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $order->external_id }}</td>
                                        <td class="font-mono text-xs">{{ (string) data_get($order->payload, 'marker', '—') }}</td>
                                        <td class="text-xs text-base-content/60">{{ $order->synced_at?->fdatetime() ?? '—' }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.orgamax.invoices.convert') }}" class="inline"
                                                  data-confirm-dialog data-confirm="{{ __('orgamax.invoice.convert_confirm') }}">
                                                @csrf
                                                <input type="hidden" name="order_id" value="{{ $order->external_id }}">
                                                <button type="submit" class="btn btn-xs btn-outline">{{ __('orgamax.invoice.convert') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Rechnungs-Projektion (Herkunft orgaMAX) --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax.invoices.heading') }}</h2>
                <p class="mb-2 text-xs text-base-content/50">{{ __('orgamax.invoices.hint') }}</p>
                @if ($invoices->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('orgamax.invoices.empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('orgamax.invoices.number') }}</th>
                                    <th>{{ __('orgamax.invoices.status') }}</th>
                                    <th>{{ __('orgamax.invoices.customer') }}</th>
                                    <th class="text-right">{{ __('orgamax.invoices.gross') }}</th>
                                    <th>{{ __('orgamax.invoices.synced') }}</th>
                                    <th class="text-right">{{ __('orgamax.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $projection)
                                    @php $p = (array) $projection->payload; @endphp
                                    <tr>
                                        <td>{{ (string) ($p['number'] ?? '—') }}</td>
                                        <td><span class="badge badge-ghost badge-sm">{{ (string) ($p['status'] ?? '—') }}</span></td>
                                        <td class="text-sm">{{ (string) ($p['customer'] ?? '—') }}</td>
                                        <td class="text-right tabular-nums">{{ $p['total_gross'] ?? '—' }}</td>
                                        <td class="text-xs text-base-content/60">{{ $projection->synced_at?->fdatetime() ?? '—' }}</td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-1">
                                                <a class="btn btn-xs btn-ghost" href="{{ route('admin.orgamax.invoices.pdf', $projection->external_id) }}">PDF</a>
                                                <form method="POST" action="{{ route('admin.orgamax.invoices.lock', $projection->external_id) }}" class="inline"
                                                      data-confirm-dialog data-confirm="{{ __('orgamax.invoice.lock_confirm') }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-outline btn-error">{{ __('orgamax.invoice.lock') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Sync-Protokoll --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('orgamax.sync.heading') }}</h2>
                @if ($connection->last_sync_counters)
                    <ul class="text-sm">
                        @foreach ((array) $connection->last_sync_counters as $key => $value)
                            <li><span class="font-mono text-xs">{{ $key }}</span>: {{ $value }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-base-content/60">{{ __('orgamax.sync.never') }}</p>
                @endif
                @if ($connection->last_error)
                    <p class="mt-1 text-sm text-error">{{ __('orgamax.sync.error', ['error' => $connection->last_error]) }}</p>
                @endif
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
