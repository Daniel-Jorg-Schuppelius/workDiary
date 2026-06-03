@extends('layouts.app')
@section('title', __('Lieferanten') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Lieferanten'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $suppliers */
    $sort = $sort ?? 'name';
    $dir = $dir ?? 'asc';
    $status = $status ?? 'active';
    $search = $search ?? '';
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Lieferanten des Mandanten verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="download" size="sm"
                    :href="route('suppliers.export', array_filter(['status' => $status, 'q' => $search]))"
                    show-label>{{ __('CSV-Export') }}</x-icon-btn>
        @can('create', App\Models\Supplier::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('suppliers.create')"
                        show-label>{{ __('Lieferant anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('suppliers.index')" :reset="$search !== '' ? route('suppliers.index', ['status' => $status]) : null">
        <input type="hidden" name="status" value="{{ $status }}">
        <x-filter-field :label="__('Suche')" for="supp-q" class="flex-1 min-w-60">
            <input id="supp-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
    </x-filter-bar>

    {{-- Tabs: Status --}}
    <div role="tablist" class="tabs tabs-box self-start">
        <a role="tab" href="{{ route('suppliers.index', ['status' => 'active', 'q' => $search]) }}"
           class="tab {{ $status === 'active' ? 'tab-active' : '' }}">{{ __('Aktiv') }}</a>
        <a role="tab" href="{{ route('suppliers.index', ['status' => 'archived', 'q' => $search]) }}"
           class="tab {{ $status === 'archived' ? 'tab-active' : '' }}">{{ __('Archiv') }}</a>
    </div>

    @if ($suppliers->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>' :title="$search !== '' ? __('Keine Lieferanten für „:q“ gefunden.', ['q' => $search]) : __('Noch keine Lieferanten in dieser Ansicht')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table table-sort="server"
                     :route="route('suppliers.index')"
                     :current-sort="$sort"
                     :current-dir="$dir"
                     :sort-params="['status' => $status, 'q' => $search]"
                     bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th></th>
                        <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                        <x-table.th sort="number">{{ __('Nr.') }}</x-table.th>
                        <x-table.th sort="company">{{ __('Firma') }}</x-table.th>
                        <th>{{ __('E-Mail') }}</th>
                        <th>{{ __('Ort') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($suppliers as $supplier)
                    <tr class="hover">
                        <td>
                            <span class="inline-block h-3 w-3 rounded-full"
                                  style="background:{{ $supplier->color ?: '#94a3b8' }}"></span>
                        </td>
                        <td>
                            <a class="link link-hover font-medium" href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                            @if ($supplier->isArchived())
                                <x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('archiviert') }}</x-status-badge>
                            @endif
                            @if (! $supplier->active)
                                <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('inaktiv') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-base-content/70 tabular-nums">{{ $supplier->number }}</td>
                        <td class="text-base-content/70">{{ $supplier->company }}</td>
                        <td class="text-base-content/70">{{ $supplier->email }}</td>
                        <td class="text-base-content/70">
                            {{ trim(($supplier->address_zip ? $supplier->address_zip.' ' : '').($supplier->address_city ?? '')) }}
                        </td>
                        <td class="text-right">
                            @can('update', $supplier)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('suppliers.edit', $supplier)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-pagination :paginator="$suppliers" />
    @endif
</x-index-page>
@endsection
