@extends('layouts.app')
@section('title', __('Fremdkunden') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Fremdkunden'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $foreignCustomers */
    $sort = $sort ?? 'name';
    $dir = $dir ?? 'asc';
    $status = $status ?? 'active';
    $search = $search ?? '';
    $customerParam = $customerFilter?->sqid;
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Endkunden der Kunden verwalten (z. B. die Kundschaft einer betreuten Firma).')">
    <x-slot:actions>
        @can('create', App\Models\ForeignCustomer::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('foreign-customers.create', array_filter(['customer' => $customerParam]))"
                        show-label>{{ __('Fremdkunde anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @if ($customerFilter)
        <div class="alert alert-info text-sm">
            {{ __('Gefiltert auf Kunde: :name', ['name' => $customerFilter->company ?: $customerFilter->name]) }}
            <a class="link" href="{{ route('foreign-customers.index') }}">{{ __('Filter entfernen') }}</a>
        </div>
    @endif

    <x-filter-bar :action="route('foreign-customers.index')" :reset="$search !== '' ? route('foreign-customers.index', array_filter(['status' => $status, 'customer' => $customerParam])) : null">
        <input type="hidden" name="status" value="{{ $status }}">
        @if ($customerParam)<input type="hidden" name="customer" value="{{ $customerParam }}">@endif
        <x-filter-field :label="__('Suche')" for="fc-q" class="flex-1 min-w-60">
            <input id="fc-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
    </x-filter-bar>

    <div role="tablist" class="tabs tabs-box self-start">
        <a role="tab" href="{{ route('foreign-customers.index', array_filter(['status' => 'active', 'q' => $search, 'customer' => $customerParam])) }}"
           class="tab {{ $status === 'active' ? 'tab-active' : '' }}">{{ __('Aktiv') }}</a>
        <a role="tab" href="{{ route('foreign-customers.index', array_filter(['status' => 'archived', 'q' => $search, 'customer' => $customerParam])) }}"
           class="tab {{ $status === 'archived' ? 'tab-active' : '' }}">{{ __('Archiv') }}</a>
    </div>

    @if ($foreignCustomers->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">groups</span>' :title="$search !== '' ? __('Keine Fremdkunden für „:q“ gefunden.', ['q' => $search]) : __('Noch keine Fremdkunden in dieser Ansicht')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table table-sort="server"
                     :route="route('foreign-customers.index')"
                     :current-sort="$sort"
                     :current-dir="$dir"
                     :sort-params="array_filter(['status' => $status, 'q' => $search, 'customer' => $customerParam])"
                     bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th></th>
                        <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                        <x-table.th sort="company">{{ __('Firma') }}</x-table.th>
                        <th>{{ __('Kunde') }}</th>
                        <th>{{ __('E-Mail') }}</th>
                        <th class="text-right">{{ __('Projekte') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($foreignCustomers as $fc)
                    <tr class="hover">
                        <td>
                            <span class="inline-block h-3 w-3 rounded-full"
                                  style="background:{{ $fc->color ?: '#94a3b8' }}"></span>
                        </td>
                        <td>
                            <a class="link link-hover font-medium" href="{{ route('foreign-customers.show', $fc) }}">{{ $fc->name }}</a>
                            @if ($fc->isArchived())
                                <x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('archiviert') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-base-content/70">{{ $fc->company }}</td>
                        <td class="text-base-content/70">
                            @if ($fc->customer)
                                <a class="link link-hover" href="{{ route('customers.show', $fc->customer) }}">{{ $fc->customer->company ?: $fc->customer->name }}</a>
                            @endif
                        </td>
                        <td class="text-base-content/70">{{ $fc->email }}</td>
                        <td class="text-right tabular-nums">{{ $fc->projects_count }}</td>
                        <td class="text-right">
                            @can('update', $fc)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('foreign-customers.edit', $fc)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-pagination :paginator="$foreignCustomers" />
    @endif
</x-index-page>
@endsection
