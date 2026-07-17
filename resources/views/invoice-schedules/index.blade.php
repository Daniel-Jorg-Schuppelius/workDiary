{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\InvoiceSchedule> $schedules
 * @var array<int, bool> $blocked
 */
@endphp

@section('nav-title', __('Abrechnungspläne'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Wiederkehrende Rechnungen: der Scheduler erzeugt ausschließlich Entwürfe — Ausstellung und Versand bleiben manuell.')">

    <x-filter-bar :action="route('invoice-schedules.index')" :reset="route('invoice-schedules.index')">
        <x-slot:extra>
            <x-icon-btn icon="arrow_back" size="sm"
                        :href="route('invoices.index')"
                        show-label>{{ __('Zu den Rechnungen') }}</x-icon-btn>
            @can(\App\Enums\User\Permission::InvoiceCreate->value)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('invoice-schedules.create') . '?dialog=1'"
                            show-label>{{ __('Neuer Plan') }}</x-icon-btn>
            @endcan
        </x-slot:extra>
    </x-filter-bar>

    <x-table table-sort="client" scroll="flex" :pinRows="true" :zebra="true" size="sm">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Titel') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                <th>{{ __('Vertrag') }}</th>
                <th>{{ __('Intervall') }}</th>
                <x-table.th sort type="date" default>{{ __('Nächster Lauf') }}</x-table.th>
                <th>{{ __('Ende') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="w-px"></th>
            </tr>
        </x-slot:head>
        @forelse ($schedules as $schedule)
            <tr class="hover">
                <td class="font-medium">
                    <a href="{{ route('invoice-schedules.show', $schedule) }}" class="link link-hover">{{ $schedule->title }}</a>
                </td>
                <td>{{ $schedule->customer?->company ?: $schedule->customer?->name ?? '—' }}</td>
                <td class="text-base-content/60">{{ $schedule->contract?->title ?? '—' }}</td>
                <td>{{ __('alle :count :unit', ['count' => $schedule->interval_count, 'unit' => $schedule->unitLabel()]) }}</td>
                <td class="whitespace-nowrap" data-sort-value="{{ $schedule->next_run_on->format('Y-m-d') }}">{{ $schedule->next_run_on->fdate() }}</td>
                <td class="whitespace-nowrap">{{ $schedule->end_on?->fdate() ?? '—' }}</td>
                <td>
                    <x-status-badge size="sm" :tone="$schedule->statusTone()">{{ $schedule->statusLabel() }}</x-status-badge>
                    @if (($blocked[$schedule->id] ?? false) === true)
                        {{-- Rechnungshoheit: externes Programm führt die Faktura — Plan läuft nicht. --}}
                        <span class="tooltip tooltip-left" data-tip="{{ __('Externes Fakturasystem führt die Rechnungen dieses Kunden — der Plan erzeugt keine Entwürfe.') }}">
                            <x-status-badge size="sm" tone="error">{{ __('Blockiert') }}</x-status-badge>
                        </span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-1">
                        @can(\App\Enums\User\Permission::InvoiceUpdate->value)
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('invoice-schedules.edit', $schedule) . '?dialog=1'"
                                        :label="__('Bearbeiten')" />
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">event_repeat</span>' :colspan="8" :title="__('Keine Abrechnungspläne angelegt')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$schedules" standing />

</x-index-page>
@endsection
