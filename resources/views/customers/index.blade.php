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
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Kunden') }}</h1>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('customers.index') }}" class="join">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                       class="input input-sm input-bordered join-item w-48">
                <button type="submit" class="btn btn-sm btn-ghost join-item">{{ __('Suchen') }}</button>
            </form>
            <div class="join">
                <a href="{{ route('customers.index', ['status' => 'active', 'q' => $search]) }}"
                   class="join-item btn btn-sm {{ $status === 'active' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Aktiv') }}</a>
                <a href="{{ route('customers.index', ['status' => 'billable_pending', 'q' => $search]) }}"
                   class="join-item btn btn-sm {{ $status === 'billable_pending' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Bereit zur Abrechnung') }}</a>
                <a href="{{ route('customers.index', ['status' => 'archived', 'q' => $search]) }}"
                   class="join-item btn btn-sm {{ $status === 'archived' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Archiv') }}</a>
            </div>
            <a href="{{ route('customers.export', array_filter(['status' => $status, 'q' => $search])) }}"
               class="btn btn-sm btn-ghost">{{ __('CSV-Export') }}</a>
            @if (auth()->user()?->canManageBilling())
                <a href="{{ route('customers.import.form') }}" class="btn btn-sm btn-ghost">{{ __('CSV-Import') }}</a>
                <form method="POST" action="{{ route('customers.lexoffice.push-all') }}"
                      onsubmit="return confirm('{{ __('Alle nicht synchronisierten Kunden zu Lexoffice übertragen?') }}');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('Lexoffice: alle pushen') }}</button>
                </form>
            @endif
            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">+ {{ __('Kunde') }}</a>
            @endcan
        </div>
    </div>

    @if ($customers->total() === 0)
        <div class="rounded-box border border-base-300 bg-base-100 p-10 text-center text-base-content/60">
            @if ($search !== '')
                {{ __('Keine Kunden für „:q" gefunden.', ['q' => $search]) }}
            @else
                {{ __('Noch keine Kunden in dieser Ansicht.') }}
            @endif
        </div>
    @else
        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
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

        <div class="px-1">
            {{ $customers->links() }}
        </div>
    @endif
</div>
@endsection
