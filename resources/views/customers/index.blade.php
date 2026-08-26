{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Kunden') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Kunden'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $customers */
    $sort = $sort ?? 'name';
    $dir = $dir ?? 'asc';
    $status = $status ?? 'active';
    $search = $search ?? '';
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Kunden des Mandanten verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="download" size="sm"
                    :href="route('customers.export', array_filter(['status' => $status, 'q' => $search]))"
                    show-label>{{ __('CSV-Export') }}</x-icon-btn>
        @if (auth()->user()?->canManageBilling())
            <x-icon-btn icon="merge" size="sm"
                        :href="route('customers.duplicates.index')"
                        show-label>{{ __('Kunden-Abgleich') }}</x-icon-btn>
            <x-icon-btn icon="upload" size="sm"
                        :href="route('admin.imports.create', ['entity' => 'customers'])"
                        show-label>{{ __('CSV-Import') }}</x-icon-btn>
            @if ($lexofficeEnabled ?? false)
                <x-action-form :action="route('customers.lexoffice.push-all')"
                      :confirm="__('Alle nicht synchronisierten Kunden zu Lexoffice übertragen?')"
                      confirm-icon="sync"
                      confirm-tone="info"
                      :confirm-label="__('Synchronisieren')">
                    <x-icon-btn icon="sync" type="submit" size="sm"
                                show-label>{{ __('Lexoffice: alle pushen') }}</x-icon-btn>
                </x-action-form>
            @endif
        @endif
        @can('create', App\Models\Customer::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('customers.create')"
                        show-label>{{ __('Kunde anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('customers.index')" :reset="$search !== '' ? route('customers.index', ['status' => $status]) : null">
        <input type="hidden" name="status" value="{{ $status }}">
        <x-filter-field :label="__('Suche')" for="cust-q" class="flex-1 min-w-60">
            <input id="cust-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
    </x-filter-bar>

    {{-- Tabs: Status --}}
    {{-- Status-Tabs über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav :items="[
        ['label' => __('Aktiv'), 'route' => 'customers.index', 'params' => ['status' => 'active', 'q' => $search], 'active' => $status === 'active'],
        ['label' => __('Bereit zur Abrechnung'), 'route' => 'customers.index', 'params' => ['status' => 'billable_pending', 'q' => $search], 'active' => $status === 'billable_pending'],
        ['label' => __('Archiv'), 'route' => 'customers.index', 'params' => ['status' => 'archived', 'q' => $search], 'active' => $status === 'archived'],
    ]" />

    @if ($customers->total() === 0)
        <x-empty-state framed icon="business" :title="$search !== '' ? __('Keine Kunden für „:q“ gefunden.', ['q' => $search]) : __('Noch keine Kunden in dieser Ansicht')" />
    @else
        <x-table :zebra="true" table-sort="server"
                 :route="route('customers.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="['status' => $status, 'q' => $search]"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th></th>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="number">{{ __('Nr.') }}</x-table.th>
                    <x-table.th sort="company">{{ __('Firma') }}</x-table.th>
                    <th>{{ __('E-Mail') }}</th>
                    <th>{{ __('Ort') }}</th>
                    <th class="text-right">{{ __('Stundensatz') }}</th>
                    <th class="text-right">{{ __('Projekte') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($customers as $customer)
                <tr class="hover">
                    <td>
                        <span class="inline-block h-3 w-3 rounded-full"
                              style="background:{{ $customer->color ?: '#94a3b8' }}"></span>
                    </td>
                    <td>
                        <a class="link link-hover font-medium" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a>
                        @if ($customer->isArchived())
                            <x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('archiviert') }}</x-status-badge>
                        @endif
                        @if (! $customer->billable)
                            <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('nicht abrechenbar') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-base-content/70 tabular-nums">{{ $customer->number }}</td>
                    <td class="text-base-content/70">{{ $customer->company }}</td>
                    <td class="text-base-content/70">{{ $customer->email }}</td>
                    <td class="text-base-content/70">
                        {{ trim(($customer->address_zip ? $customer->address_zip.' ' : '').($customer->address_city ?? '')) }}
                    </td>
                    <td class="text-right tabular-nums">
                        @if ($customer->hourly_rate !== null)
                            {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($customer->hourly_rate?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $customer->currency->value }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $customer->projects_count }}</td>
                    <td class="text-right">
                        @can('update', $customer)
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('customers.edit', $customer)"
                                        :label="__('Bearbeiten')" />
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$customers" standing />
    @endif
</x-index-page>
@endsection
