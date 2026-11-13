{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Leistungsplan, Jahressicht (Feature 199, MVP-1638): Kunden × Leistungen eines Jahres. Variablen: $result, $rows (Paginator), $filters, $linkParams, $years, $members, $customerGroups, $viewAll, $canManage. --}}

@extends('layouts.app')

@section('title', __('service_plan.title'))
@section('nav-title', __('service_plan.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('service_plan.subtitle.year', ['year' => $result->query->year])">
    <x-slot:actions>
        @include('service-plan._toolbar_actions', ['route' => 'service-plan.index'])
    </x-slot:actions>

    <div class="flex flex-wrap items-center justify-between gap-2">
        @include('service-plan._tabs')
        @unless ($viewAll)
            <p role="status" class="text-xs text-muted"><x-icon name="visibility" /> {{ __('service_plan.hint.own_cells') }}</p>
        @endunless
    </div>

    @include('service-plan._tiles', ['route' => 'service-plan.index'])
    @include('service-plan._filters', ['route' => 'service-plan.index'])

    @if ($rows->isEmpty())
        <x-empty-state framed icon="calendar_view_month" :title="__('service_plan.empty.title')" :message="__('service_plan.empty.year')" />
    @else
        <x-table scroll="flex" :pinRows="true" :caption="__('service_plan.caption.year', ['year' => $result->query->year])">
            <x-slot:head>
                <tr>
                    <th class="sticky left-0 z-10 bg-base-200">{{ __('service_plan.column.customer') }}</th>
                    @foreach ($result->columns as $column)
                        <th class="min-w-28 align-bottom">{{ $column->label }}</th>
                    @endforeach
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                @php($customerSqid = \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $row->customerId))
                <tr data-plan-row="{{ $customerSqid }}">
                    <th scope="row" class="sticky left-0 z-10 bg-base-100 font-normal">
                        <a class="link link-hover font-medium" href="{{ route('customers.show', $customerSqid) }}">{{ $row->customerName }}</a>
                        @if ($row->customerNumber)
                            <div class="text-xs text-muted">{{ $row->customerNumber }}</div>
                        @endif
                    </th>
                    @foreach ($result->columns as $column)
                        @include('service-plan._cell', ['cell' => $row->cells[$column->service->key()] ?? null, 'showResponsible' => true])
                    @endforeach
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$rows" standing />
    @endif
</x-index-page>
@endsection
