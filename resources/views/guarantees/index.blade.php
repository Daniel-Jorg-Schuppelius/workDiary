{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bürgschaftsregister (Feature 114, MVP-603): gestellte und erhaltene
  Bürgschaften mit Befristung, Rückgabe-Nachweis und Einbehalts-Ablösung.
--}}

@extends('layouts.app')

@section('title', __('guarantee.title'))
@section('nav-title', __('guarantee.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('guarantee.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('guarantees.create')"
                        show-label>{{ __('guarantee.action.create') }}</x-icon-btn>
        </x-slot:actions>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('guarantee.kpi.issued')" :value="$activeIssued" format="int"
                        :hint="__('guarantee.kpi.issued_hint')" />
            <x-kpi-tile :label="__('guarantee.kpi.received')" :value="$activeReceived" format="int"
                        :hint="__('guarantee.kpi.received_hint')" />
            <x-kpi-tile :label="__('guarantee.kpi.expiring')" :value="$expiringSoon" format="int"
                        :tone="$expiringSoon > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('guarantee.kpi.return_due')" :value="$returnDue" format="int"
                        :tone="$returnDue > 0 ? 'warning' : 'neutral'"
                        :hint="__('guarantee.kpi.return_due_hint')" />
        </div>

        <x-filter-bar :action="route('guarantees.index')" :reset="route('guarantees.index')">
            <x-filter-field :label="__('guarantee.filter.direction')" for="g-direction" class="min-w-44">
                <select id="g-direction" name="direction" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Guarantee\GuaranteeDirection::cases() as $d)
                        <option value="{{ $d->value }}" @selected($filters['direction'] === $d->value)>{{ $d->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('guarantee.filter.status')" for="g-status" class="min-w-44">
                <select id="g-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Guarantee\GuaranteeStatus::cases() as $st)
                        <option value="{{ $st->value }}" @selected($filters['status'] === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('guarantee.column.reference') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('guarantee.column.direction') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('guarantee.column.kind') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('guarantee.column.issuer') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('guarantee.column.party') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('guarantee.column.amount') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('guarantee.column.expires_on') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('guarantee.column.status') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($guarantees as $guarantee)
                <tr class="hover">
                    <td class="whitespace-nowrap font-medium">{{ $guarantee->reference ?? '—' }}</td>
                    <td>{{ $guarantee->direction->label() }}</td>
                    <td>{{ $guarantee->kind->label() }}</td>
                    <td>{{ $guarantee->issuerLabel() }}</td>
                    <td>{{ $guarantee->customer?->displayLabel() ?? $guarantee->supplier?->displayLabel() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($guarantee->amount->toFloat(), 2, withThousandsSeparator: true) }}</td>
                    <td class="whitespace-nowrap">
                        @if ($guarantee->expires_on === null)
                            <span class="text-base-content/50">{{ __('guarantee.unlimited') }}</span>
                        @else
                            <x-status-badge :tone="$guarantee->isExpiredUnnoticed() ? 'error' : 'neutral'" outline>{{ $guarantee->expires_on->fdate() }}</x-status-badge>
                        @endif
                    </td>
                    <td><x-status-badge :tone="$guarantee->status->tone()">{{ $guarantee->status->label() }}</x-status-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('guarantees.edit', $guarantee)"
                                        :label="__('Bearbeiten')" />
                            @if ($guarantee->status->isActive())
                                <x-action-form :action="route('guarantees.returned', $guarantee)">
                                    <x-icon-btn icon="assignment_return" size="xs" tone="ghost" type="submit"
                                                :label="__('guarantee.action.returned')" />
                                </x-action-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9" icon="gpp_maybe" :title="__('guarantee.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$guarantees" standing />
    </x-index-page>
@endsection
