@extends('layouts.app')
@section('title', __('Kunden') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Kunden'))

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $customers */
    $sort = $sort ?? 'name';
    $dir = $dir ?? 'asc';
    $status = $status ?? 'active';
    $search = $search ?? '';
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <a href="{{ route('customers.export', array_filter(['status' => $status, 'q' => $search])) }}"
                   class="btn btn-sm btn-ghost gap-1">
                    <x-icon name="download" />
                    <span>{{ __('CSV-Export') }}</span>
                </a>
                @if (auth()->user()?->canManageBilling())
                    <a href="{{ route('customers.import.form') }}" class="btn btn-sm btn-ghost gap-1">
                    <x-icon name="upload" />
                    <span>{{ __('CSV-Import') }}</span>
                </a>
                <form method="POST" action="{{ route('customers.lexoffice.push-all') }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Alle nicht synchronisierten Kunden zu Lexoffice übertragen?') }}"
                      data-confirm-icon="sync"
                      data-confirm-tone="info"
                      data-confirm-label="{{ __('Synchronisieren') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost gap-1">
                        <x-icon name="sync" />
                        <span>{{ __('Lexoffice: alle pushen') }}</span>
                    </button>
                </form>
            @endif
            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                    <x-icon name="add" />
                    <span>{{ __('Kunde') }}</span>
                </a>
            @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('customers.index')" :reset="$search !== '' ? route('customers.index', ['status' => $status]) : null">
        <input type="hidden" name="status" value="{{ $status }}">
        <x-filter-field :label="__('Suche')" for="cust-q" class="flex-1 min-w-60">
            <input id="cust-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
    </x-filter-bar>

    {{-- Tabs: Status --}}
    <div role="tablist" class="tabs tabs-box self-start">
        <a role="tab" href="{{ route('customers.index', ['status' => 'active', 'q' => $search]) }}"
           class="tab {{ $status === 'active' ? 'tab-active' : '' }}">{{ __('Aktiv') }}</a>
        <a role="tab" href="{{ route('customers.index', ['status' => 'billable_pending', 'q' => $search]) }}"
           class="tab {{ $status === 'billable_pending' ? 'tab-active' : '' }}">{{ __('Bereit zur Abrechnung') }}</a>
        <a role="tab" href="{{ route('customers.index', ['status' => 'archived', 'q' => $search]) }}"
           class="tab {{ $status === 'archived' ? 'tab-active' : '' }}">{{ __('Archiv') }}</a>
    </div>

    @if ($customers->total() === 0)
        <x-card>
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">business</span>' :title="$search !== '' ? __('Keine Kunden für „:q“ gefunden.', ['q' => $search]) : __('Noch keine Kunden in dieser Ansicht')" />
        </x-card>
    @else
        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('customers.index')"
                     :current-sort="$sort"
                     :current-dir="$dir"
                     :sort-params="['status' => $status, 'q' => $search]"
                     bare>
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
                                <span class="badge badge-ghost badge-xs ml-1">{{ __('archiviert') }}</span>
                            @endif
                            @if (! $customer->billable)
                                <span class="badge badge-warning badge-xs ml-1">{{ __('nicht abrechenbar') }}</span>
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
                                {{ number_format((float) $customer->hourly_rate, 2, ',', '.') }} {{ $customer->currency }}
                            @else
                                <span class="text-base-content/40">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $customer->projects_count }}</td>
                        <td class="text-right">
                            @can('update', $customer)
                                <a href="{{ route('customers.edit', $customer) }}" data-entry-modal-trigger
                                   class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        @if ($customers->hasPages())
            <div class="px-1">
                {{ $customers->links() }}
            </div>
        @endif
    @endif
</x-page-shell>
@endsection
