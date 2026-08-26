{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : usage-report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('ai.usage.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('ai.usage.title'))

@section('content')
<x-index-page :subtitle="__('ai.usage.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="download" size="sm"
                    :href="route('admin.ai.usage', ['export' => 'csv'])"
                    show-label>{{ __('CSV') }}</x-icon-btn>
        <x-icon-btn icon="table_view" size="sm"
                    :href="route('admin.ai.usage', ['export' => 'xlsx'])"
                    show-label>Excel</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.ai.index')" show-label>{{ __('ai.title.connections') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Budgetauslastung laufender Monat je Familie --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ($families as $family)
            @php $b = $currentBudget[$family->value]; @endphp
            <x-kpi-tile :label="$family->label()"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $b['used'], 0, withThousandsSeparator: true)"
                        :tone="$b['percent'] !== null && $b['percent'] >= 90 ? 'warning' : 'info'"
                        :hint="$b['limit'] !== null
                            ? __('ai.usage.of_limit', ['limit' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $b['limit'], 0, withThousandsSeparator: true), 'percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $b['percent'], 1)])
                            : __('ai.usage.unlimited')" />
        @endforeach
    </div>

    {{-- Monatsverlauf (12 Monate) --}}
    <x-card :title="__('ai.usage.months')" padding="p-0">
        <x-table bare :caption="__('ai.usage.months')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Monat') }}</x-table.th>
                    @foreach ($families as $family)
                        <x-table.th align="right">{{ $family->label() }}</x-table.th>
                    @endforeach
                </tr>
            </x-slot:head>
            @foreach ($months as $row)
                <tr>
                    <td class="font-mono text-xs">{{ $row['period'] }}</td>
                    @foreach ($families as $family)
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $row['families'][$family->value], 0, withThousandsSeparator: true) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-table>
    </x-card>

    {{-- Vorschlags-Funnel je Capability --}}
    <x-card :title="__('ai.usage.funnel')" padding="p-0">
        @if ($funnel->isEmpty())
            <div class="p-4">
                <x-empty-state icon="psychology" :title="__('ai.usage.funnel_empty')" tone="ghost" />
            </div>
        @else
            <x-table bare :caption="__('ai.usage.funnel')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('ai.field.capability') }}</x-table.th>
                        <x-table.th align="right">{{ __('ai.usage.proposed') }}</x-table.th>
                        <x-table.th align="right">{{ __('ai.usage.adopted') }}</x-table.th>
                        <x-table.th align="right">{{ __('ai.usage.rejected') }}</x-table.th>
                        <x-table.th align="right">{{ __('ai.usage.adoption_rate') }}</x-table.th>
                        <x-table.th align="right">{{ __('ai.usage.cached') }}</x-table.th>
                        <x-table.th align="right">{{ __('ai.usage.fallbacks') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($funnel as $capability => $stats)
                    <tr>
                        <td>
                            <div>{{ \App\Services\Ai\Dto\AiCapability::labelFor((string) $capability) }}</div>
                            <div class="font-mono text-xs text-muted">{{ $capability }}</div>
                        </td>
                        <td class="text-right tabular-nums">{{ $stats['total'] }}</td>
                        <td class="text-right tabular-nums">{{ ($stats['byStatus']['accepted'] ?? 0) + ($stats['byStatus']['edited'] ?? 0) }}</td>
                        <td class="text-right tabular-nums">{{ $stats['byStatus']['rejected'] ?? 0 }}</td>
                        <td class="text-right tabular-nums">{{ $stats['adoptionPercent'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $stats['adoptionPercent'], 1) . ' %' : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $stats['cached'] }}</td>
                        <td class="text-right tabular-nums">{{ $stats['fallbacks'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-index-page>
@endsection
