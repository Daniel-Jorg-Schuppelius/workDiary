@extends('layouts.app')
@section('title', __('Kunden') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Kunden'))

@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $customers */
    $sort = $sort ?? 'name';
    $dir = $dir ?? 'asc';
    $status = $status ?? 'active';
    $search = $search ?? '';
    $sortLink = function (string $col) use ($sort, $dir, $status, $search) {
        $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        return route('customers.index', array_filter([
            'status' => $status, 'q' => $search, 'sort' => $col, 'dir' => $newDir,
        ]));
    };
    $arrow = fn(string $c) => $sort === $c ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <form method="GET" action="{{ route('customers.index') }}" class="join">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                       class="input input-sm input-bordered join-item w-full sm:w-48">
                <button type="submit" class="btn btn-sm btn-ghost join-item gap-1">
                    <x-icon name="search" />
                    <span class="hidden sm:inline">{{ __('Suchen') }}</span>
                </button>
            </form>
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
            <x-empty-state :title="$search !== '' ? __('Keine Kunden für „:q“ gefunden.', ['q' => $search]) : __('Noch keine Kunden in dieser Ansicht')" />
        </x-card>
    @else
        <x-card padding="p-0">
            <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th></th>
                        <th><a class="link link-hover" href="{{ $sortLink('name') }}">{{ __('Name') }}{{ $arrow('name') }}</a></th>
                        <th><a class="link link-hover" href="{{ $sortLink('number') }}">{{ __('Nr.') }}{{ $arrow('number') }}</a></th>
                        <th><a class="link link-hover" href="{{ $sortLink('company') }}">{{ __('Firma') }}{{ $arrow('company') }}</a></th>
                        <th>{{ __('E-Mail') }}</th>
                        <th>{{ __('Ort') }}</th>
                        <th class="text-right">{{ __('Stundensatz') }}</th>
                        <th class="text-right">{{ __('Projekte') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
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
                </tbody>
            </table>
            </div>
        </x-card>

        <div class="px-1">
            {{ $customers->links() }}
        </div>
    @endif
</x-page-shell>
@endsection
