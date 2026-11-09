{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('legacy_support.nav.customers') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('legacy_support.nav.customers'))

@include('partials.page-fill')

@section('content')
    @php
        $customerSqid = fn (int $id): string => \App\Support\Sqid::encode(\App\Legacy\Models\LegacySupportCustomer::class, $id);
    @endphp
    <x-index-page overflow="clip" :subtitle="__('legacy_support.customers.subtitle')">
        <x-slot:actions>
            @if ($writeEnabled)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('legacy.support.customers.create')"
                            show-label>{{ __('legacy_support.customers.create') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="support" size="sm" :href="route('legacy.support.orders.index')" show-label>{{ __('legacy_support.nav.orders') }}</x-icon-btn>
        </x-slot:actions>

        @unless ($writeEnabled)
            <div class="alert alert-info flex-none text-sm">{{ __('legacy_support.read_only') }}</div>
        @endunless

        <x-table scroll="flex" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string" default="asc">{{ __('legacy_support.field.company') }}</x-table.th>
                    <x-table.th sort type="string" class="w-40">{{ __('legacy_support.field.username') }}</x-table.th>
                    <x-table.th sort type="number" class="w-32 text-right">{{ __('legacy_support.customers.open_orders') }}</x-table.th>
                    <x-table.th sort type="number" class="w-32 text-right">{{ __('legacy_support.customers.archived_orders') }}</x-table.th>
                    <th class="w-28 text-right">{{ __('legacy_support.field.actions') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($customers as $customer)
                <tr class="hover">
                    <td>
                        <a href="{{ route('legacy.support.orders.index', ['customer' => $customerSqid((int) $customer->id), 'year' => 'all']) }}" class="link link-hover">{{ $customer->kunde }}</a>
                    </td>
                    <td class="font-mono">{{ $customer->usern }}</td>
                    <td class="text-right">{{ $customer->orders_count }}</td>
                    <td class="text-right">{{ $customer->archived_orders_count }}</td>
                    <td class="whitespace-nowrap text-right">
                        @if ($writeEnabled)
                            <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('legacy.support.customers.edit', $customer)" :label="__('legacy_support.customers.edit')" />
                            <form method="POST" action="{{ route('legacy.support.customers.destroy', $customer) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('legacy_support.customers.delete') }}"
                                  data-confirm-message="{{ __('legacy_support.customers.delete_confirm', ['name' => $customer->kunde]) }}"
                                  data-confirm-label="{{ __('legacy_support.customers.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('legacy_support.customers.delete')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="badge" :colspan="5" :title="__('legacy_support.customers.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
