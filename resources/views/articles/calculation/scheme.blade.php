{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : scheme.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kalkulationsschema und Lohngruppen (MVP-1055). Erwartet: $scheme, $kinds, $wageGroups, $averageWage, $labourHourCost --}}
@extends('layouts.app')
@section('title', __('article.calculation.scheme_title'))
@section('nav-title', __('article.title'))

@php
    $eur = static fn (?\CommonToolkit\ValueObjects\Money $m): string => $m === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($m->toFloat(), 2, withThousandsSeparator: true) . ' €';
    $markup = static function ($scheme, \App\Enums\Article\CostKind $kind, string $field): string {
        $row = $scheme->markups->first(fn ($m) => $m->cost_kind === $kind);

        return $row?->{$field}?->getNumericValue() ?? '0';
    };
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('article.calculation.scheme_title')" :subtitle="__('article.calculation.scheme_subtitle')" />
    </x-slot:toolbar>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-kpi-tile :label="__('article.calculation.kpi.average_wage')" :value="$eur($averageWage)" />
        <x-kpi-tile :label="__('article.calculation.kpi.labour_hour_cost')" :value="$eur($labourHourCost)" :hint="__('article.calculation.hint.labour_hour_cost')" />
    </div>

    <form method="POST" action="{{ route('articles.calculation-scheme.update') }}" class="space-y-4" data-entry-form>
        @csrf
        @method('PUT')
        <x-card :title="__('article.calculation.wage_costs')">
            <div class="grid gap-2 sm:grid-cols-3">
                <x-input-field name="average_wage_amount" type="number" min="0" step="0.01" :label="__('article.calculation.field.average_wage')"
                               :value="old('average_wage_amount', $scheme->average_wage_amount?->getAmount())" :hint="__('article.calculation.hint.average_wage')" />
                <x-input-field name="wage_related_percent" type="number" min="0" step="0.01" required :label="__('article.calculation.field.wage_related')"
                               :value="old('wage_related_percent', $scheme->wage_related_percent->getNumericValue())" :hint="__('article.calculation.hint.wage_related')" />
                <x-input-field name="wage_ancillary_amount" type="number" min="0" step="0.01" required :label="__('article.calculation.field.wage_ancillary')"
                               :value="old('wage_ancillary_amount', $scheme->wage_ancillary_amount->getAmount())" :hint="__('article.calculation.hint.wage_ancillary')" />
            </div>
        </x-card>
        <x-card :title="__('article.calculation.markups')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('article.calculation.col.kind') }}</th>
                        <th class="text-right">{{ __('article.calculation.col.site_overhead') }}</th>
                        <th class="text-right">{{ __('article.calculation.col.general_overhead') }}</th>
                        <th class="text-right">{{ __('article.calculation.col.risk_profit') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($kinds as $kind)
                    <tr>
                        <td>{{ $kind->label() }}</td>
                        @foreach (['site_overhead_percent', 'general_overhead_percent', 'risk_profit_percent'] as $field)
                            <td class="text-right">
                                <input type="number" min="0" max="500" step="0.01" required
                                       name="markups[{{ $kind->value }}][{{ $field }}]"
                                       aria-label="{{ $kind->label() }} — {{ __('article.calculation.col.' . str_replace('_percent', '', $field)) }}"
                                       value="{{ old('markups.' . $kind->value . '.' . $field, $markup($scheme, $kind, $field)) }}"
                                       class="input input-sm input-bordered w-24 text-right">
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-validation-errors />
        <div class="flex justify-end"><x-button type="submit">{{ __('Speichern') }}</x-button></div>
    </form>

    <x-card :title="__('article.calculation.wage_groups')" padding="p-0">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('articles.wage-groups.create')" show-label>{{ __('article.calculation.add_wage_group') }}</x-icon-btn>
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('article.calculation.col.wage_group') }}</th>
                    <th class="text-right">{{ __('article.calculation.col.hourly_wage') }}</th>
                    <th class="text-right">{{ __('article.calculation.col.headcount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($wageGroups as $group)
                <tr>
                    <td>{{ $group->name }}</td>
                    <td class="text-right tabular-nums">{{ $eur($group->hourly_wage_amount) }}</td>
                    <td class="text-right tabular-nums">{{ $group->headcount }}</td>
                    <td>{{ $group->is_active ? __('aktiv') : __('inaktiv') }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('articles.wage-groups.edit', $group)" :title="__('Bearbeiten')" />
                        <x-action-form :action="route('articles.wage-groups.destroy', $group)" method="DELETE"
                                       :confirm="__('article.calculation.delete_wage_group_confirm')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('Entfernen')">
                            <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                        </x-action-form>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="badge" :colspan="5" :title="__('article.calculation.no_wage_groups')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
