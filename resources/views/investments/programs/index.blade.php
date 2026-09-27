{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Investitionsprogramme (MVP-927). Erwartet: $programs --}}
@extends('layouts.app')

@section('title', __('investment.program.title'))
@section('nav-title', __('investment.program.title'))

@section('content')
<x-index-page :subtitle="__('investment.program.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('investments.index')" :label="__('Investitionen')" />
        @can(\App\Enums\User\Permission::InvestmentManage->value)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('investments.programs.create')" show-label>{{ __('investment.program.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.program.field.name') }}</th>
                    <th>{{ __('investment.program.field.years') }}</th>
                    <th class="text-right">{{ __('investment.program.field.budget_total') }}</th>
                    <th class="text-right">{{ __('investment.program.field.cases') }}</th>
                    <th>{{ __('investment.program.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($programs as $program)
                <tr>
                    <td><a class="link" href="{{ route('investments.programs.show', $program) }}">{{ $program->name }}</a></td>
                    <td class="tabular-nums">{{ $program->starts_year }}–{{ $program->ends_year }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((string) $program->budgets->sum('budget_amount'), 2, withThousandsSeparator: true) }} {{ $program->currency->value }}</td>
                    <td class="text-right tabular-nums">{{ $program->cases_count }}</td>
                    <td><x-status-badge size="md" outline>{{ $program->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('investments.programs.show', $program)" :label="__('investment.program.show')" /></td>
                </tr>
            @empty
                <x-table.empty icon="account_tree" :colspan="6" :title="__('investment.program.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
