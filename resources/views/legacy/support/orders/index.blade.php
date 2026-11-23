{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('legacy_support.orders.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('legacy_support.orders.title'))

@include('partials.page-fill')

@section('content')
    @php
        $orderModel = \App\Legacy\Models\LegacySupportOrder::class;
        $customerSqid = fn (int $id): string => \App\Support\Sqid::encode(\App\Legacy\Models\LegacySupportCustomer::class, $id);
        $tileHref = fn (string $status): string => route('legacy.support.orders.index', array_filter([...$filters, 'status' => $status]));
        $tiles = [
            ['label' => __('legacy_support.orders.tile_all'), 'value' => $counts['all'], 'tone' => 'neutral', 'status' => ''],
            ['label' => $orderModel::statusLabelFor($orderModel::STATUS_NEW), 'value' => $counts[$orderModel::STATUS_NEW], 'tone' => 'info', 'status' => (string) $orderModel::STATUS_NEW],
            ['label' => $orderModel::statusLabelFor($orderModel::STATUS_SEEN), 'value' => $counts[$orderModel::STATUS_SEEN], 'tone' => 'warning', 'status' => (string) $orderModel::STATUS_SEEN],
            ['label' => $orderModel::statusLabelFor($orderModel::STATUS_SCHEDULED), 'value' => $counts[$orderModel::STATUS_SCHEDULED], 'tone' => 'primary', 'status' => (string) $orderModel::STATUS_SCHEDULED],
            ['label' => __('legacy_support.status.overdue'), 'value' => $counts['overdue'], 'tone' => 'error', 'status' => 'overdue'],
            ['label' => $orderModel::statusLabelFor($orderModel::STATUS_DONE), 'value' => $counts[$orderModel::STATUS_DONE], 'tone' => 'success', 'status' => (string) $orderModel::STATUS_DONE],
            ['label' => $orderModel::statusLabelFor($orderModel::STATUS_ARCHIVED), 'value' => $counts[$orderModel::STATUS_ARCHIVED], 'tone' => 'neutral', 'status' => (string) $orderModel::STATUS_ARCHIVED],
        ];
        $mailOn = $mailSetting?->isEnabled() ?? false;
        $sortParams = array_filter([...$filters, 'status' => $statusFilter]);
    @endphp

    <x-index-page overflow="clip" :subtitle="__('legacy_support.orders.subtitle')">
        <x-slot:actions>
            @if ($writeEnabled)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('legacy.support.orders.create', array_filter(['customer' => $filters['customer'] ?? null]))"
                            show-label>{{ __('legacy_support.orders.create') }}</x-icon-btn>
                <x-icon-btn :icon="$mailOn ? 'mark_email_read' : 'unsubscribe'" size="sm"
                            data-entry-modal-trigger
                            :href="route('legacy.support.mail.edit')"
                            show-label>{{ $mailOn ? __('legacy_support.mail.state_on') : __('legacy_support.mail.state_off') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="badge" size="sm" :href="route('legacy.support.customers.index')" show-label>{{ __('legacy_support.nav.customers') }}</x-icon-btn>
            <x-icon-btn icon="inventory_2" size="sm" :href="route('legacy.support.archive.index')" show-label>{{ __('legacy_support.nav.archive') }}</x-icon-btn>
        </x-slot:actions>

        @unless ($writeEnabled)
            <div class="alert alert-info flex-none text-sm">{{ __('legacy_support.read_only') }}</div>
        @endunless

        <div class="grid flex-none gap-3 sm:grid-cols-4 xl:grid-cols-7">
            @foreach ($tiles as $tile)
                <x-kpi-tile :label="$tile['label']" :value="$tile['value']" :tone="$tile['tone']"
                            :href="$tileHref($tile['status'])"
                            :active="$statusFilter === $tile['status']" />
            @endforeach
        </div>

        <x-filter-bar :action="route('legacy.support.orders.index')" :reset="route('legacy.support.orders.index')">
            @if ($statusFilter !== '')
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif
            <select name="year" class="select select-sm select-bordered w-36 shrink-0" aria-label="{{ __('legacy_support.filter.year') }}">
                @foreach ($years as $optionYear)
                    <option value="{{ $optionYear }}" @selected($year === $optionYear)>{{ $optionYear }}</option>
                @endforeach
                <option value="all" @selected($year === null)>{{ __('legacy_support.filter.all_years') }}</option>
            </select>
            <select name="customer" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('legacy_support.field.customer') }}">
                <option value="">{{ __('legacy_support.filter.all_customers') }}</option>
                @foreach ($customers as $customer)
                    @php $sqid = $customerSqid((int) $customer->id); @endphp
                    <option value="{{ $sqid }}" @selected(($filters['customer'] ?? '') === $sqid)>{{ $customer->kunde }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" class="input input-sm input-bordered w-64 shrink-0"
                   aria-label="{{ __('legacy_support.filter.search') }}" placeholder="{{ __('legacy_support.filter.search_hint') }}">
        </x-filter-bar>

        <x-table scroll="flex" table-sort="server" :route="route('legacy.support.orders.index')" :current-sort="$sort" :current-dir="$dir" :sort-params="$sortParams">
            <x-slot:head>
                <tr>
                    <x-table.th sort="nr" class="w-20">{{ __('legacy_support.field.number') }}</x-table.th>
                    <x-table.th sort="datum" class="w-28">{{ __('legacy_support.field.date') }}</x-table.th>
                    <x-table.th sort="kunde">{{ __('legacy_support.field.customer') }}</x-table.th>
                    <x-table.th sort="telefon" class="w-36">{{ __('legacy_support.field.phone') }}</x-table.th>
                    <x-table.th sort="termin" class="w-40">{{ __('legacy_support.field.appointment') }}</x-table.th>
                    <x-table.th sort="status" class="w-44">{{ __('legacy_support.field.status') }}</x-table.th>
                    <x-table.th sort="objekt">{{ __('legacy_support.field.object_order') }}</x-table.th>
                    <th class="w-28 text-right">{{ __('legacy_support.field.actions') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($orders as $order)
                @php $appointment = $order->appointmentAt(); @endphp
                <tr class="hover">
                    <td class="font-mono">
                        <a href="{{ route('legacy.support.orders.show', $order) }}" data-entry-modal-trigger class="link link-hover">{{ $order->id }}</a>
                    </td>
                    <td class="whitespace-nowrap">{{ $order->datum?->fdate() ?? '—' }}</td>
                    <td>{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</td>
                    <td class="whitespace-nowrap">{{ $order->telefon !== '' ? $order->telefon : '—' }}</td>
                    <td @class(['whitespace-nowrap', 'font-semibold text-error' => $order->isOverdue()])>{{ \App\Legacy\Support\LegacyClock::format($appointment) ?? '—' }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            <x-status-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-status-badge>
                            @if ($order->isOverdue())
                                <x-status-badge tone="error" icon="schedule">{{ __('legacy_support.status.overdue') }}</x-status-badge>
                            @endif
                        </div>
                    </td>
                    <td class="text-sm">
                        <a href="{{ route('legacy.support.orders.show', $order) }}" data-entry-modal-trigger class="link link-hover">
                            {{ \CommonToolkit\Helper\Data\StringHelper::truncate(trim($order->objekt . ', ' . $order->auftrag, ', '), 120) }}
                        </a>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <x-icon-btn icon="visibility" data-entry-modal-trigger :href="route('legacy.support.orders.show', $order)" :label="__('legacy_support.orders.show')" />
                        @if ($writeEnabled)
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('legacy.support.orders.edit', $order)" :label="__('legacy_support.orders.edit')" />
                            <form method="POST" action="{{ route('legacy.support.orders.destroy', $order) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('legacy_support.orders.delete') }}"
                                  data-confirm-message="{{ __('legacy_support.orders.delete_confirm', ['number' => $order->id]) }}"
                                  data-confirm-label="{{ __('legacy_support.orders.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('legacy_support.orders.delete')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="support" :colspan="8" :title="__('legacy_support.orders.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$orders" standing />
    </x-index-page>
@endsection
