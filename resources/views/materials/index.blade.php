{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Materialien'))
@section('nav-title', __('Materialien'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('Materialien und Verbrauchsmittel verwalten.')">
    <x-filter-bar :action="route('materials.index')" :reset="$q !== '' ? route('materials.index') : null">
        <x-filter-field :label="__('Suche')" for="mat-q" class="flex-1 min-w-60">
            <input id="mat-q" type="search" name="q" value="{{ $q }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-slot:extra>
            @can('create', \App\Models\Material::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('materials.create')"
                            show-label>{{ __('Material') }}</x-icon-btn>
            @endcan
        </x-slot:extra>
    </x-filter-bar>

    <x-table :zebra="true" table-sort="server"
             :route="route('materials.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'"
             :sort-params="['q' => $q]"
             scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <x-table.th sort="sku">SKU</x-table.th>
                <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                <x-table.th sort="unit">{{ __('Einheit') }}</x-table.th>
                <x-table.th sort="price" align="right">{{ __('EP netto') }}</x-table.th>
                <x-table.th sort="tax" align="right">USt %</x-table.th>
                <x-table.th sort="provider">{{ __('Quelle') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse($materials as $m)
            <tr>
                <td>{{ $m->sku }}</td>
                <td>{{ $m->name }}</td>
                <td>{{ $m->unit }}</td>
                <td class="text-right">{{ $m->default_unit_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($m->default_unit_price?->toFloat() ?? 0.0), 4, withThousandsSeparator: true) : '—' }}</td>
                <td class="text-right">{{ $m->tax_rate !== null ? rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((float) ($m->tax_rate?->getNumericValue() ?? '0')), 2, withThousandsSeparator: true), '0'), ',') : '—' }}</td>
                <td>{{ $m->external_provider ?: 'local' }}</td>
                <td class="text-right">
                    @can('update', $m)
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('materials.edit', $m)"
                                    :label="__('Bearbeiten')" />
                    @endcan
                    @can('delete', $m)
                        <x-action-form :action="route('materials.destroy', $m)" method="DELETE"
                              :confirm="__('Löschen?')"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>' :colspan="7" :title="__('Noch keine Materialien')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$materials" standing />
</x-index-page>
@endsection
