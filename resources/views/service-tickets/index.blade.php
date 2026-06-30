{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Service-Tickets'))
@section('nav-title', __('Service-Tickets'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Service- & FM-Tickets mit SLA-Übersicht.')">
    <x-slot:actions>
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        :href="route('service-tickets.create')"
                        show-label>{{ __('Ticket anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('service-tickets.index')" :reset="route('service-tickets.index')">
        <input type="text" name="q" value="{{ $filters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />

        <select name="status" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach ($statusOptions as $val => $label)
                <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="priority" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Priorität') }}">
            <option value="">{{ __('Alle Prio.') }}</option>
            @foreach ($priorityOptions as $val => $label)
                <option value="{{ $val }}" @selected($filters['priority'] === $val)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="assignee" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Bearbeiter') }}">
            <option value="">{{ __('Alle Bearbeiter') }}</option>
            <option value="me" @selected($filters['assignee'] === 'me')>{{ __('Mir zugewiesen') }}</option>
            <option value="unassigned" @selected($filters['assignee'] === 'unassigned')>{{ __('Unzugewiesen') }}</option>
        </select>
    </x-filter-bar>

    @if ($tickets->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">support_agent</span>' />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('service-tickets.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['q' => $filters['q'] ?: null, 'status' => $filters['status'] ?: null, 'priority' => $filters['priority'] ?: null, 'assignee' => $filters['assignee'] ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="ticket_no">{{ __('Ticket') }}</x-table.th>
                    <x-table.th sort="title">{{ __('Titel') }}</x-table.th>
                    <th>{{ __('Asset / Kunde') }}</th>
                    <x-table.th sort="priority">{{ __('Priorität') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <th>{{ __('Bearbeiter') }}</th>
                    <th>{{ __('SLA') }}</th>
                    <x-table.th sort="resolution_due_at">{{ __('Lösung bis') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($tickets as $ticket)
                @php
                    $resDue = $ticket->resolution_due_at;
                    $slaStatus = $ticket->slaStatus();
                    $remaining = $ticket->slaMinutesRemaining();
                    $dueClass = $slaStatus->textClass();
                @endphp
                <tr class="hover">
                    <td class="font-mono text-xs">
                        <a href="{{ route('service-tickets.show', $ticket) }}" class="link link-hover">{{ $ticket->ticket_no }}</a>
                    </td>
                    <td>
                        <a href="{{ route('service-tickets.show', $ticket) }}" class="link link-hover font-medium">{{ $ticket->title }}</a>
                    </td>
                    <td class="text-base-content/70 text-xs">
                        @if ($ticket->asset)
                            <span class="material-symbols-outlined text-[14px] align-middle">inventory_2</span>
                            {{ $ticket->asset->name }}
                        @endif
                        @if ($ticket->customer)
                            <div>{{ $ticket->customer->name }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-sm">{{ $ticket->priority->label() }}</span>
                    </td>
                    <td>
                        <x-status-badge size="sm" outline>{{ $ticket->status->label() }}</x-status-badge>
                    </td>
                    <td class="text-base-content/70">{{ $ticket->assignedTo?->name ?: '—' }}</td>
                    <td>
                        <x-status-badge :tone="$slaStatus->tone()" size="sm" outline>{{ $slaStatus->label() }}</x-status-badge>
                    </td>
                    <td class="{{ $dueClass }}">
                        {{ $resDue?->translatedFormat('d.m.Y H:i') ?: '—' }}
                        @if ($remaining !== null && $slaStatus->value !== 'met' && $slaStatus->value !== 'none')
                            <div class="text-[11px] opacity-70">
                                {{ $remaining < 0 ? __('sla.overdue_by', ['min' => abs($remaining)]) : __('sla.remaining', ['min' => $remaining]) }}
                            </div>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-icon-btn icon="open_in_new" :href="route('service-tickets.show', $ticket)" :label="__('Details')" />
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$tickets" standing />
    @endif
</x-index-page>
@endsection
