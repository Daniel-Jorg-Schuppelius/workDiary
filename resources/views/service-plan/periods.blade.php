{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : periods.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Leistungsplan, Periodensicht (Feature 199, MVP-1639): Kunde × Leistung je Monat, Quartal oder Halbjahr eines Jahres, mit Zusatzspalten aus den Merkmalen des Regelsatzes. Variablen: $result, $rows (Paginator), $filters, $linkParams, $years, $members, $customerGroups, $ruleSets, $derived, $months, $viewAll, $canManage. --}}

@extends('layouts.app')

@section('title', __('service_plan.title'))
@section('nav-title', __('service_plan.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('service_plan.subtitle.periods', ['year' => $result->query->year])">
    <x-slot:actions>
        @if ($canManage && $result->columns !== [])
            <x-icon-btn icon="event_repeat" tone="ghost" size="sm" data-entry-modal-trigger
                        :href="route('service-plan.periods.create-dialog', ['year' => $result->query->year, 'dialog' => 1])"
                        show-label>{{ __('service_plan.create_periods.title') }}</x-icon-btn>
        @endif
        @include('service-plan._toolbar_actions', ['route' => 'service-plan.periods.index'])
    </x-slot:actions>

    <div class="flex flex-wrap items-center justify-between gap-2">
        @include('service-plan._tabs')
        @unless ($viewAll)
            <p role="status" class="text-xs text-muted"><x-icon name="visibility" /> {{ __('service_plan.hint.own_cells') }}</p>
        @endunless
    </div>

    @include('service-plan._tiles', ['route' => 'service-plan.periods.index'])
    @include('service-plan._filters', ['route' => 'service-plan.periods.index'])

    @if ($rows->isEmpty())
        <x-empty-state framed icon="view_week" :title="__('service_plan.empty.title')" :message="__('service_plan.empty.periods')" />
    @else
        <x-table scroll="flex" :pinRows="true" :caption="__('service_plan.caption.periods', ['year' => $result->query->year])">
            <x-slot:head>
                <tr>
                    <th class="sticky left-0 z-10 bg-base-200">{{ __('service_plan.column.customer') }}</th>
                    <th>{{ __('service_plan.column.service') }}</th>
                    <th>{{ __('service_plan.column.responsible') }}</th>
                    @foreach ($derived['labels'] ?? [] as $label)
                        <th class="text-xs">{{ $label }}</th>
                    @endforeach
                    @foreach ($result->traitFields as $field)
                        <th class="text-xs">{{ $field->label }}</th>
                    @endforeach
                    @foreach ($months as $month => $label)
                        <th class="min-w-24 text-center">{{ $label }}</th>
                    @endforeach
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                @php
                    $customerSqid = \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $row->customerId);
                    $traits = $result->traitsOf($row->customerId);
                @endphp
                <tr data-plan-row="{{ $customerSqid }}" data-plan-service="{{ $row->service?->service->param() }}">
                    <th scope="row" class="sticky left-0 z-10 bg-base-100 font-normal">
                        <a class="link link-hover font-medium" href="{{ route('customers.show', $customerSqid) }}">{{ $row->customerName }}</a>
                        @if ($row->customerNumber)
                            <div class="text-xs text-muted">{{ $row->customerNumber }}</div>
                        @endif
                    </th>
                    <td class="whitespace-nowrap">
                        {{ $row->service?->label }}
                        <div class="text-xs text-muted">{{ $row->service?->kind->label() }}</div>
                    </td>
                    <td class="whitespace-nowrap text-sm">{{ $result->responsibleName($row->responsibleId) ?? '—' }}</td>
                    @foreach ($derived['labels'] ?? [] as $label)
                        <td class="text-xs">{{ $derived['values'][$row->customerId][$label] ?? '—' }}</td>
                    @endforeach
                    @foreach ($result->traitFields as $field)
                        <td class="text-xs">
                            @if ($traits !== null && ! $traits->isEmpty($field))
                                <x-field-display :field="$field" :values="$traits" />
                            @else
                                —
                            @endif
                        </td>
                    @endforeach
                    @foreach ($row->cells as $cell)
                        @include('service-plan._cell', ['cell' => $cell, 'showResponsible' => false])
                    @endforeach
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$rows" standing />
    @endif
</x-index-page>
@endsection
