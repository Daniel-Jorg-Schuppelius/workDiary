{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vorschau der Listenübernahme (Feature 199, MVP-1640). Variablen: $analysis, $filename, $year, $serviceLabel, $createOrders. --}}
@extends('layouts.app')
@section('title', __('service_plan.import.preview_title'))
@section('nav-title', __('service_plan.import.title'))

@php
    $valid = count(array_filter($analysis['rows'], static fn (array $row): bool => $row['issues'] === []));
    $invalid = count($analysis['rows']) - $valid;
    $periodLabels = [];
    foreach (array_keys($analysis['periods']) as $periodKey) {
        $periodLabels[$periodKey] = \App\Support\Period\PeriodKey::parse((string) $periodKey)->label();
    }
@endphp

@section('content')
<x-index-page :subtitle="__('service_plan.import.preview_subtitle', ['file' => $filename, 'year' => $year])" back-route="service-plan.import.create" :back-label="__('service_plan.import.title')">

    @if ($analysis['errors'] !== [])
        <div role="alert" class="alert alert-error text-sm">
            <x-icon name="error" />
            <ul class="list-disc pl-4">
                @foreach ($analysis['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-card :title="__('service_plan.import.summary')" icon="fact_check">
        <x-detail-grid layout="split" cols="2" small-labels>
            <x-detail-grid.row :label="__('service_plan.import.list_kind')">{{ __('service_plan.import.kind.' . $analysis['kind']) }}@if ($analysis['kind'] === 'periods') · {{ $analysis['period_kind']->label() }}@endif</x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.import.current_period')">{{ $analysis['current']->label() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.column.service')">{{ $serviceLabel ?? __('service_plan.import.service_from_column') }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.import.create_orders')">{{ $createOrders ? __('service_plan.yes') : __('service_plan.no') }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.import.rows_valid')">{{ $valid }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.import.rows_invalid')">{{ $invalid }}</x-detail-grid.row>
            @if ($analysis['traits'] !== [])
                <x-detail-grid.row :label="__('service_plan.import.trait_columns')">{{ implode(', ', array_keys($analysis['traits'])) }}</x-detail-grid.row>
            @endif
        </x-detail-grid>
        @if ($analysis['errors'] === [] && $valid > 0)
            <form method="POST" action="{{ route('service-plan.import.store') }}" class="mt-4 flex justify-end">
                @csrf
                <x-icon-btn icon="done_all" tone="primary" type="submit" show-label>{{ __('service_plan.import.apply', ['count' => $valid]) }}</x-icon-btn>
            </form>
        @endif
    </x-card>

    <x-table :caption="__('service_plan.import.preview_title')">
        <x-slot:head>
            <tr>
                <th>{{ __('service_plan.import.line') }}</th>
                <th>{{ __('service_plan.column.customer_number') }}</th>
                <th>{{ __('service_plan.column.customer') }}</th>
                <th>{{ __('service_plan.column.service') }}</th>
                @if ($analysis['kind'] === 'year')
                    <th>{{ __('service_plan.column.state') }}</th>
                @else
                    @foreach ($periodLabels as $label)
                        <th class="text-xs">{{ $label }}</th>
                    @endforeach
                @endif
                <th>{{ __('service_plan.column.note') }}</th>
                <th>{{ __('service_plan.import.messages') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($analysis['rows'] as $row)
            <tr @class(['bg-error/5' => $row['issues'] !== []]) data-import-line="{{ $row['line'] }}">
                <td class="tabular-nums">{{ $row['line'] }}</td>
                <td>{{ $row['number'] !== '' ? $row['number'] : '—' }}</td>
                <td>{{ $row['customer_name'] ?? ($row['name'] !== '' ? $row['name'] : '—') }}</td>
                <td>{{ $row['service_label'] ?? '—' }}</td>
                @foreach ($row['states'] as $state)
                    <td class="text-xs">{{ $state !== null ? \App\Enums\ServicePlan\ServicePlanCellState::from($state)->label() : '—' }}</td>
                @endforeach
                <td class="max-w-48 truncate text-xs" title="{{ $row['note'] }}">{{ $row['note'] ?? '' }}</td>
                <td class="text-xs">
                    @foreach ($row['issues'] as $issue)
                        <div class="text-error">{{ $issue }}</div>
                    @endforeach
                    @foreach ($row['warnings'] as $warning)
                        <div class="text-warning">{{ $warning }}</div>
                    @endforeach
                    @if ($row['issues'] === [] && $row['warnings'] === [])
                        <span class="text-success">{{ __('service_plan.import.row_ok') }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-table>
</x-index-page>
@endsection
