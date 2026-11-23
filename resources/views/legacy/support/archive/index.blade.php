{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('legacy_support.archive.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('legacy_support.archive.title'))

@include('partials.page-fill')

@section('content')
    @php
        $customerSqid = fn (int $id): string => \App\Support\Sqid::encode(\App\Legacy\Models\LegacySupportCustomer::class, $id);
    @endphp

    <x-index-page overflow="clip" :subtitle="__('legacy_support.archive.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="support" size="sm" :href="route('legacy.support.orders.index')" show-label>{{ __('legacy_support.nav.orders') }}</x-icon-btn>
        </x-slot:actions>

        @if ($writeEnabled)
            <details class="flex-none rounded-box border border-base-300 bg-base-100 shadow-xs" @if ($errors->has('before')) open @endif>
                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold">
                    {{ __('legacy_support.archive.run_title') }}
                    <span class="ml-2 badge badge-sm badge-ghost">{{ trans_choice('legacy_support.archive.eligible', $eligibleCount, ['count' => $eligibleCount, 'date' => $cutoff->fdate()]) }}</span>
                </summary>
                <form method="POST" action="{{ route('legacy.support.archive.run') }}" class="flex flex-wrap items-end gap-3 px-4 pb-4 pt-2">
                    @csrf
                    <div>
                        <label for="support-archive-before" class="mb-1 block text-xs font-semibold">{{ __('legacy_support.archive.before') }}</label>
                        <input id="support-archive-before" name="before" type="date" value="{{ old('before', $cutoff->format('Y-m-d')) }}" class="input input-bordered input-sm" required>
                        @error('before')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <p class="max-w-xl text-xs text-muted">{{ __('legacy_support.archive.run_hint') }}</p>
                    <x-icon-btn icon="inventory_2" tone="warning" size="sm" type="submit"
                                data-confirm-dialog
                                data-confirm-title="{{ __('legacy_support.archive.run_title') }}"
                                data-confirm-message="{{ __('legacy_support.archive.run_confirm') }}"
                                data-confirm-label="{{ __('legacy_support.archive.run') }}"
                                show-label>{{ __('legacy_support.archive.run') }}</x-icon-btn>
                </form>
            </details>
        @endif

        <x-filter-bar :action="route('legacy.support.archive.index')" :reset="route('legacy.support.archive.index')">
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
            <x-slot:extra>
                <span class="text-sm text-muted">{{ trans_choice('legacy_support.archive.count', $orders->total(), ['count' => $orders->total()]) }}</span>
            </x-slot:extra>
        </x-filter-bar>

        <x-table scroll="flex" table-sort="server" :route="route('legacy.support.archive.index')" :current-sort="$sort" :current-dir="$dir" :sort-params="$filters">
            <x-slot:head>
                <tr>
                    <x-table.th sort="nr" class="w-20">{{ __('legacy_support.field.number') }}</x-table.th>
                    <x-table.th sort="datum" class="w-28">{{ __('legacy_support.field.date') }}</x-table.th>
                    <x-table.th sort="kunde">{{ __('legacy_support.field.customer') }}</x-table.th>
                    <x-table.th sort="telefon" class="w-36">{{ __('legacy_support.field.phone') }}</x-table.th>
                    <x-table.th sort="termin" class="w-40">{{ __('legacy_support.field.appointment') }}</x-table.th>
                    <x-table.th sort="objekt">{{ __('legacy_support.field.object_order') }}</x-table.th>
                    <th class="w-16 text-right">{{ __('legacy_support.field.actions') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($orders as $order)
                <tr class="hover">
                    <td class="font-mono">
                        <a href="{{ route('legacy.support.archive.show', $order) }}" data-entry-modal-trigger class="link link-hover">{{ $order->id }}</a>
                    </td>
                    <td class="whitespace-nowrap">{{ $order->datum?->fdate() ?? '—' }}</td>
                    <td>{{ $order->customer?->kunde ?? __('legacy_support.customer_unknown', ['id' => $order->kunde]) }}</td>
                    <td class="whitespace-nowrap">{{ $order->telefon !== '' ? $order->telefon : '—' }}</td>
                    <td class="whitespace-nowrap">{{ \App\Legacy\Support\LegacyClock::format($order->appointmentAt()) ?? '—' }}</td>
                    <td class="text-sm">
                        <a href="{{ route('legacy.support.archive.show', $order) }}" data-entry-modal-trigger class="link link-hover">
                            {{ \CommonToolkit\Helper\Data\StringHelper::truncate(trim($order->objekt . ', ' . $order->auftrag, ', '), 120) }}
                        </a>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <x-icon-btn icon="visibility" data-entry-modal-trigger :href="route('legacy.support.archive.show', $order)" :label="__('legacy_support.orders.show')" />
                    </td>
                </tr>
            @empty
                <x-table.empty icon="inventory_2" :colspan="7" :title="__('legacy_support.archive.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$orders" standing />
    </x-index-page>
@endsection
