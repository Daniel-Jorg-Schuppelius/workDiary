{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Investitionsprogramm mit Portfolio (MVP-927). Erwartet: $program, $portfolio --}}
@extends('layouts.app')

@section('title', $program->name)
@section('nav-title', __('investment.program.title'))

@php
    $money = static fn (string|float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true);
    $canManage = Gate::allows(\App\Enums\User\Permission::InvestmentManage->value);
@endphp

@section('content')
<x-page-shell>
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="$program->name"
                        back-route="investments.programs.index" :back-label="__('investment.program.title')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline>{{ $program->status->label() }}</x-status-badge>
                <span class="badge badge-outline">{{ $program->starts_year }}–{{ $program->ends_year }}</span>
                <span class="badge badge-outline">{{ $program->currency->value }}</span>
            </div>
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('investments.programs.edit', $program)" show-label>{{ __('investment.program.edit') }}</x-icon-btn>
                    @foreach ($program->status->allowedTransitions() as $target)
                        <form method="POST" action="{{ route('investments.programs.status', $program) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $target->value }}">
                            <x-button type="submit" size="sm">{{ __('investment.program.transition.' . $target->value) }}</x-button>
                        </form>
                    @endforeach
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($program->description)
        <p class="whitespace-pre-line text-sm">{{ $program->description }}</p>
    @endif

    <x-card :title="__('investment.program.section.years')" icon="calendar_month" padding="p-0">
        <form method="POST" action="{{ route('investments.programs.budgets', $program) }}">
            @csrf
            @method('PUT')
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('investment.program.field.year') }}</th>
                        <th class="text-right">{{ __('investment.program.field.budget') }}</th>
                        <th class="text-right">{{ __('investment.program.field.planned') }}</th>
                        <th class="text-right">{{ __('investment.program.field.approved') }}</th>
                        <th class="text-right">{{ __('investment.program.field.actual') }}</th>
                        <th class="text-right">{{ __('investment.program.field.remaining') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($portfolio['years'] as $row)
                    <tr>
                        <td class="tabular-nums">{{ $row['year'] }}</td>
                        <td class="text-right">
                            @if ($canManage)
                                <input type="number" step="0.01" min="0" name="budget[{{ $row['year'] }}]" value="{{ $row['budget'] !== '0.00' ? $row['budget'] : '' }}"
                                       class="input input-xs input-bordered w-32 text-right" aria-label="{{ __('investment.program.field.budget') }} {{ $row['year'] }}">
                            @else
                                <span class="tabular-nums">{{ $money($row['budget']) }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $money($row['planned']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['approved']) }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['actual']) }}</td>
                        <td class="text-right tabular-nums {{ $row['over'] ? 'text-error font-semibold' : '' }}">{{ $money($row['remaining']) }}</td>
                    </tr>
                @endforeach
            </x-table>
            @if ($canManage)
                <div class="flex justify-end p-3"><x-button type="submit" size="sm" icon="save">{{ __('investment.program.save_budgets') }}</x-button></div>
            @endif
        </form>
    </x-card>

    <x-card :title="__('investment.program.section.cases')" icon="trending_up" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.program.field.case') }}</th>
                    <th>{{ __('investment.program.field.year') }}</th>
                    <th>{{ __('investment.program.field.category') }}</th>
                    <th>{{ __('investment.program.field.status') }}</th>
                    <th class="text-right">{{ __('investment.program.field.planned') }}</th>
                    <th class="text-right">{{ __('investment.program.field.actual') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($portfolio['cases'] as $row)
                <tr>
                    <td><a class="link" href="{{ route('investments.show', $row['case']) }}">{{ $row['case']->title }}</a></td>
                    <td class="tabular-nums">{{ $row['year'] }}</td>
                    <td>{{ __('values.' . $row['case']->category) }}</td>
                    <td>{{ __('values.' . $row['case']->status) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['planned']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['actual']) }}</td>
                </tr>
            @empty
                <x-table.empty icon="trending_up" :colspan="6" :title="__('investment.program.no_cases')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
