@extends('layouts.app')
@section('title', __('billbee.title'))
@section('nav-title', __('billbee.title'))

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
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('billbee.title') }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($openInbox > 0)
                        <span class="badge badge-warning badge-sm">{{ __('billbee.open_inbox', ['count' => $openInbox]) }}</span>
                    @endif
                    @if ($lastSyncAt)
                        <span class="badge badge-ghost badge-sm">{{ __('billbee.last_sync', ['at' => \Illuminate\Support\Carbon::parse($lastSyncAt)->diffForHumans()]) }}</span>
                    @endif
                    <form method="POST" action="{{ route('admin.billbee.sync') }}">
                        @csrf
                        <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('billbee.action.sync') }}</x-icon-btn>
                    </form>
                </div>
            </div>
            <p class="mb-1 text-sm text-base-content/60">{{ __('billbee.intro') }}</p>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('admin.billbee.index') }}" class="flex flex-wrap items-end gap-2">
            <label class="form-control">
                <span class="label-text text-xs">{{ __('billbee.field.channel') }}</span>
                <select name="channel" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('billbee.filter.all_channels') }}</option>
                    @foreach ($channels as $option)
                        <option value="{{ $option }}" @selected($channel === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-control">
                <span class="label-text text-xs">{{ __('billbee.field.state') }}</span>
                <input type="number" name="state" value="{{ $state }}" class="input input-sm input-bordered w-24" min="0" max="99" />
            </label>
            <button type="submit" class="btn btn-sm">{{ __('billbee.filter.apply') }}</button>
        </form>

        {{-- Bestellspiegel --}}
        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('billbee.field.order_number') }}</th>
                        <th>{{ __('billbee.field.channel') }}</th>
                        <th>{{ __('billbee.field.state') }}</th>
                        <th>{{ __('billbee.field.buyer') }}</th>
                        <th>{{ __('billbee.field.customer') }}</th>
                        <th class="text-right">{{ __('billbee.field.total') }}</th>
                        <th>{{ __('billbee.field.ordered_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="font-mono text-xs">{{ $order->order_number ?? $order->billbee_order_id }}</td>
                            <td><span class="badge badge-ghost badge-sm">{{ $order->channel ?? '—' }}</span></td>
                            <td>{{ $order->stateLabel() }}</td>
                            <td>{{ data_get($order->buyer, 'FullName') ?? data_get($order->buyer, 'Email') ?? '—' }}</td>
                            <td>
                                @if ($order->customer)
                                    {{ $order->customer->name }}
                                @else
                                    <span class="badge badge-warning badge-sm">{{ __('billbee.status.open_assignment') }}</span>
                                @endif
                            </td>
                            {{-- Anzeige-Makros statt Roh-Formatierung (Vollaudit 2026-07, N52). --}}
                            <td class="text-right font-mono text-xs">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($order->total_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $order->currency?->value }}</td>
                            <td class="text-xs">{{ $order->ordered_at?->fdatetime() ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" icon="shopping_cart" :title="__('billbee.empty')" compact />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$orders" standing />
    </div>
</x-page-shell>
@endsection
