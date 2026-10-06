{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('b2b_catalog.title'))
@section('nav-title', __('b2b_catalog.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('b2b_catalog.intro')" />

        {{-- Punchout-Endpunkt für die Beschaffungsseite --}}
        <x-card>
            <div class="text-sm">
                <span class="text-muted">{{ __('b2b_catalog.punchout_url') }}:</span>
                <code class="rounded bg-base-200 px-2 py-0.5">{{ $punchoutUrl }}</code>
            </div>
        </x-card>

        {{-- Neuer Zugang --}}
        <x-card>
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('b2b_catalog.access_new_heading') }}</h2>
            <p class="mb-3 text-xs text-muted">{{ __('b2b_catalog.access_new_hint') }}</p>
            <form method="POST" action="{{ route('b2b-catalog.store') }}" class="grid gap-3 md:grid-cols-4">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('b2b_catalog.field.customer') }}</span>
                    <select name="customer" required class="select select-bordered select-sm">
                        <option value="">{{ __('b2b_catalog.field.customer_placeholder') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->sqid }}" @selected(old('customer') === $customer->sqid)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('b2b_catalog.field.label') }}</span>
                    <input type="text" name="label" value="{{ old('label') }}" class="input input-bordered input-sm" required maxlength="120">
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('b2b_catalog.field.username') }}</span>
                    <input type="text" name="username" value="{{ old('username') }}" class="input input-bordered input-sm" required maxlength="64" autocomplete="off">
                </label>
                <div class="flex items-end">
                    <x-button type="submit">{{ __('b2b_catalog.action.issue') }}</x-button>
                </div>
            </form>
        </x-card>

        {{-- Zugänge --}}
        <x-card>
            <h2 class="mb-3 font-['Space_Grotesk'] text-base font-semibold">{{ __('b2b_catalog.access_heading') }}</h2>
            <x-table :bare="true" :empty-title="__('b2b_catalog.access_empty')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('b2b_catalog.field.customer') }}</th>
                        <th>{{ __('b2b_catalog.field.label') }}</th>
                        <th>{{ __('b2b_catalog.field.username') }}</th>
                        <th>{{ __('b2b_catalog.field.items_count') }}</th>
                        <th>{{ __('b2b_catalog.field.last_used') }}</th>
                        <th>{{ __('b2b_catalog.field.status') }}</th>
                        <th class="text-right">{{ __('b2b_catalog.field.actions') }}</th>
                    </tr>
                </x-slot:head>
                            @foreach ($accesses as $access)
                                <tr class="hover">
                                    <td>{{ $access->customer?->name }}</td>
                                    <td>{{ $access->label }}</td>
                                    <td><code class="text-xs">{{ $access->username }}</code></td>
                                    <td>{{ $access->items_count }}</td>
                                    <td>{{ $access->last_used_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        @if ($access->isActive())
                                            <x-status-badge tone="success">{{ __('b2b_catalog.status.active') }}</x-status-badge>
                                        @else
                                            <x-status-badge>{{ __('b2b_catalog.status.revoked') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <x-button :href="route('b2b-catalog.show', $access)" tone="ghost" size="xs">{{ __('b2b_catalog.action.manage') }}</x-button>
                                            @if ($access->isActive())
                                                <form method="POST" action="{{ route('b2b-catalog.revoke', $access) }}">
                                                    @csrf
                                                    <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('b2b_catalog.action.revoke') }}</x-button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
            </x-table>
        </x-card>

        {{-- openTRANS-Bestellungen (MVP-458) --}}
        <x-card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('b2b_catalog.orders_heading') }}</h2>
                <form method="POST" action="{{ route('b2b-catalog.orders.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                    @csrf
                    <input type="file" name="order_file" accept=".xml,text/xml,application/xml" required class="file-input file-input-bordered file-input-sm">
                    <x-button type="submit">{{ __('b2b_catalog.action.upload_order') }}</x-button>
                </form>
            </div>
            <p class="mb-3 text-xs text-muted">{{ __('b2b_catalog.orders_hint') }}</p>
            <x-table :bare="true" :empty-title="__('b2b_catalog.orders_empty')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('b2b_catalog.field.order_id') }}</th>
                        <th>{{ __('b2b_catalog.field.customer') }}</th>
                        <th>{{ __('b2b_catalog.field.source') }}</th>
                        <th class="text-right">{{ __('b2b_catalog.field.total_net') }}</th>
                        <th>{{ __('b2b_catalog.field.ordered_at') }}</th>
                        <th>{{ __('b2b_catalog.field.status') }}</th>
                    </tr>
                </x-slot:head>
                            @foreach ($orders as $order)
                                <tr class="hover">
                                    <td><code class="text-xs">{{ $order->external_order_id }}</code></td>
                                    <td>{{ $order->customer?->name ?? ($order->buyer['name'] ?? '—') }}</td>
                                    <td>{{ $order->source }}</td>
                                    <td class="text-right">{{ $order->total_net?->format() ?? '—' }}</td>
                                    <td>{{ $order->ordered_at?->fdate() ?? '—' }}</td>
                                    <td>
                                        @if ($order->status === \App\Enums\B2b\B2bOrderStatus::Open)
                                            <a href="{{ route('admin.integration.inbox') }}" class="badge badge-warning badge-sm">{{ $order->status->label() }}</a>
                                        @elseif ($order->status === \App\Enums\B2b\B2bOrderStatus::Booked)
                                            <x-status-badge tone="success">{{ $order->status->label() }}</x-status-badge>
                                        @else
                                            <x-status-badge>{{ $order->status->label() }}</x-status-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$orders" standing />
    </div>
</x-page-shell>
@endsection
