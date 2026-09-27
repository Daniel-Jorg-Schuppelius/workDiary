{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Schadensfälle (MVP-919). Erwartet: $cases, $subjectTypes, $openCount, $openEstimate, $settledTotal --}}
@extends('layouts.app')

@section('title', __('damage.title'))
@section('nav-title', __('damage.title'))

@section('content')
<x-index-page :subtitle="__('damage.subtitle')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('damage.kpi.open')" :value="$openCount" />
        <x-kpi-tile :label="__('damage.kpi.open_estimate')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($openEstimate, 2, withThousandsSeparator: true)" />
        <x-kpi-tile :label="__('damage.kpi.settled')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($settledTotal, 2, withThousandsSeparator: true)" />
    </div>

    <x-filter-bar :action="route('damage-cases.index')" :reset="route('damage-cases.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('damage.field.status') }}">
            <option value="">{{ __('damage.filter.all_status') }}</option>
            @foreach (\App\Enums\Damage\DamageCaseStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="kind" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('damage.field.kind') }}">
            <option value="">{{ __('damage.filter.all_kinds') }}</option>
            @foreach (\App\Enums\Damage\DamageKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(request('kind') === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </select>
        <select name="subject_type" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('damage.field.subject') }}">
            <option value="">{{ __('damage.filter.all_subjects') }}</option>
            @foreach ($subjectTypes as $type)
                <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ __('damage.subject_type.' . $type) }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('damage.field.number') }}</th>
                    <th>{{ __('damage.field.title') }}</th>
                    <th>{{ __('damage.field.subject') }}</th>
                    <th>{{ __('damage.field.kind') }}</th>
                    <th class="text-right">{{ __('damage.field.estimated_amount') }}</th>
                    <th class="text-right">{{ __('damage.field.settled_amount') }}</th>
                    <th>{{ __('damage.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($cases as $case)
                <tr>
                    <td><a href="{{ route('damage-cases.show', $case) }}" class="link font-mono">{{ $case->number }}</a></td>
                    <td>{{ $case->title }}</td>
                    <td>{{ $case->subject?->damageSubjectLabel() ?? '—' }}</td>
                    <td>{{ $case->kind->label() }}</td>
                    <td class="text-right tabular-nums">{{ $case->estimated_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($case->estimated_amount, 2, withThousandsSeparator: true) . ' ' . $case->currency->value : '—' }}</td>
                    <td class="text-right tabular-nums">{{ $case->settled_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($case->settled_amount, 2, withThousandsSeparator: true) . ' ' . $case->currency->value : '—' }}</td>
                    <td><x-status-badge size="md" outline :tone="$case->status->tone()">{{ $case->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('damage-cases.show', $case)" :label="__('damage.action.show')" /></td>
                </tr>
            @empty
                <x-table.empty icon="car_crash" :colspan="8" :title="__('damage.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
