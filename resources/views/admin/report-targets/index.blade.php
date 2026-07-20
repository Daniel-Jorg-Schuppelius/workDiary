{{--
  Created on   : Sun Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('reporting.target.title'))
@section('nav-title', __('reporting.target.title'))

@section('content')
<x-index-page :subtitle="__('reporting.target.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.report-targets.create')"
                    show-label>{{ __('reporting.target.create') }}</x-icon-btn>
    </x-slot:actions>

    @php
        $fmtScope = function($t) use ($scopeNames): string {
            if ($t->scope->value === 'org' || $t->scope_id === null) {
                return __('reporting.target.scope.org');
            }
            $name = $scopeNames[$t->scope->value][$t->scope_id] ?? ('#' . $t->scope_id);
            return $t->scope->label() . ': ' . $name;
        };
    @endphp

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('reporting.target.metric_label') }}</th>
                <th>{{ __('reporting.target.scope_label') }}</th>
                <th class="text-right">{{ __('reporting.target.value_label') }}</th>
                <th>{{ __('reporting.target.period_label') }}</th>
                <th>{{ __('reporting.target.valid_from') }}</th>
                <th>{{ __('reporting.target.valid_until') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($targets as $t)
            <tr>
                <td class="font-medium">{{ $t->metric->label() }}</td>
                <td>{{ $fmtScope($t) }}</td>
                <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $t->target_value, 2, withThousandsSeparator: true) }}</td>
                <td>{{ $t->period?->label() ?? '–' }}</td>
                <td>{{ $t->valid_from?->format('d.m.Y') ?? '–' }}</td>
                <td>{{ $t->valid_until?->format('d.m.Y') ?? '–' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.report-targets.edit', $t)"
                                    :label="__('Bearbeiten')" />
                        <x-action-form :action="route('admin.report-targets.destroy', $t)"
                              method="DELETE"
                              :confirm="__('reporting.target.delete_confirm')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">flag</span>' :colspan="7" :title="__('reporting.target.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$targets" standing />
</x-index-page>
@endsection
