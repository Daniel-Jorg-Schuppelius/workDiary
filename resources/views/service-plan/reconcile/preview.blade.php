{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vorschau des Kundenabgleichs (Feature 199, MVP-1640). Variablen: $analysis, $filename, $mayCreate, $mayRename. --}}
@extends('layouts.app')
@section('title', __('service_plan.reconcile.preview_title'))
@section('nav-title', __('service_plan.reconcile.title'))

@php
    $proposals = array_values(array_filter($analysis['rows'], static fn (array $row): bool => in_array($row['status'], ['new', 'renamed'], true)));
    $invalid = array_values(array_filter($analysis['rows'], static fn (array $row): bool => $row['status'] === 'invalid'));
@endphp

@section('content')
<x-index-page :subtitle="__('service_plan.reconcile.preview_subtitle', ['file' => $filename])" back-route="service-plan.reconcile.create" :back-label="__('service_plan.reconcile.title')">

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
            @foreach (['new', 'renamed', 'unchanged', 'missing', 'invalid'] as $status)
                <x-detail-grid.row :label="__('service_plan.reconcile.status.' . $status)">{{ $analysis['counts'][$status] ?? 0 }}</x-detail-grid.row>
            @endforeach
        </x-detail-grid>
        <p class="mt-2 text-xs text-muted">{{ __('service_plan.reconcile.hint.nothing_overwritten') }}</p>
    </x-card>

    @if ($proposals !== [] && $analysis['errors'] === [])
        <form method="POST" action="{{ route('service-plan.reconcile.store') }}" class="space-y-3">
            @csrf
            <x-table :caption="__('service_plan.reconcile.proposals')">
                <x-slot:head>
                    <tr>
                        <th class="w-px"><span class="sr-only">{{ __('service_plan.reconcile.confirm') }}</span></th>
                        <th>{{ __('service_plan.reconcile.column.status') }}</th>
                        <th>{{ __('service_plan.column.customer_number') }}</th>
                        <th>{{ __('service_plan.reconcile.column.list_name') }}</th>
                        <th>{{ __('service_plan.reconcile.column.current_name') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($proposals as $row)
                    @php
                        $isNew = $row['status'] === 'new';
                        $allowed = $isNew ? $mayCreate : $mayRename;
                        $checkboxId = 'reconcile-' . $row['status'] . '-' . $loop->index;
                    @endphp
                    <tr data-reconcile-number="{{ $row['number'] }}">
                        <td>
                            @if ($isNew)
                                <input type="checkbox" id="{{ $checkboxId }}" name="create_numbers[]" value="{{ $row['number'] }}" class="checkbox checkbox-sm" @disabled(! $allowed)
                                       aria-label="{{ __('service_plan.reconcile.confirm_new', ['number' => $row['number']]) }}">
                            @else
                                <input type="checkbox" id="{{ $checkboxId }}" name="rename_numbers[]" value="{{ $row['number'] }}" class="checkbox checkbox-sm" @disabled(! $allowed)
                                       aria-label="{{ __('service_plan.reconcile.confirm_renamed', ['number' => $row['number']]) }}">
                            @endif
                        </td>
                        <td><x-status-badge size="xs" :tone="$row['status'] === 'new' ? 'info' : 'warning'">{{ __('service_plan.reconcile.status.' . $row['status']) }}</x-status-badge></td>
                        <td class="tabular-nums">{{ $row['number'] }}</td>
                        <td><label for="{{ $checkboxId }}">{{ $row['name'] }}</label></td>
                        <td class="text-muted">{{ $row['current_name'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
            <div class="flex justify-end">
                <x-icon-btn icon="done_all" tone="primary" type="submit" show-label>{{ __('service_plan.reconcile.apply') }}</x-icon-btn>
            </div>
        </form>
    @endif

    @if ($analysis['missing'] !== [])
        <x-card :title="__('service_plan.reconcile.missing_title', ['count' => $analysis['missing_total']])" icon="person_off">
            <p class="mb-2 text-xs text-muted">{{ __('service_plan.reconcile.hint.missing') }}</p>
            <ul class="columns-1 text-sm sm:columns-2">
                @foreach ($analysis['missing'] as $customer)
                    <li><span class="tabular-nums text-muted">{{ $customer['number'] }}</span> {{ $customer['name'] }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($invalid !== [])
        <x-card :title="__('service_plan.reconcile.status.invalid')" icon="error">
            <ul class="text-sm">
                @foreach ($invalid as $row)
                    <li>{{ __('service_plan.import.line') }} {{ $row['line'] }}: {{ $row['issue'] }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-index-page>
@endsection
