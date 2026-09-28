{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : fixed-asset-classes.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlagenklassen (Feature 133, MVP-999): Vorgaben für Nutzungsdauer, Methode und Konten. Variablen: $classes
--}}
@extends('layouts.app')
@section('title', __('accounting.fixed_assets.classes.title'))
@section('nav-title', __('accounting.fixed_assets.classes.title'))

@section('content')
<x-index-page :subtitle="__('accounting.fixed_assets.classes.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="precision_manufacturing" size="sm" show-label :href="route('finance.accounting.fixed-assets.index')">{{ __('accounting.fixed_assets.title') }}</x-icon-btn>
        <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger
                    :href="route('finance.accounting.fixed-asset-classes.create')" :label="__('accounting.fixed_assets.classes.action.add')" />
    </x-slot:actions>

    <x-card>
        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('accounting.fixed_assets.classes.field.name') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('accounting.fixed_assets.column.useful_life') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.fixed_assets.field.method') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.fixed_assets.field.asset_account') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.fixed_assets.field.depreciation_account') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('accounting.fixed_assets.classes.field.is_active') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($classes as $class)
                <tr class="hover">
                    <td>{{ $class->name }}</td>
                    <td class="text-right tabular-nums">{{ $class->useful_life_months }}</td>
                    <td>{{ $class->depreciation_method->label() }}</td>
                    <td>{{ $class->assetAccount?->displayLabel() ?? '—' }}</td>
                    <td>{{ $class->depreciationAccount?->displayLabel() ?? '—' }}</td>
                    <td><x-status-badge :tone="$class->is_active ? 'success' : 'ghost'" size="sm">{{ $class->is_active ? __('Aktiv') : __('Inaktiv') }}</x-status-badge></td>
                    <td class="text-right whitespace-nowrap">
                        <x-icon-btn icon="edit" size="sm" tone="ghost" data-entry-modal-trigger
                                    :href="route('finance.accounting.fixed-asset-classes.edit', $class)" :label="__('accounting.fixed_assets.classes.action.edit')" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" :title="__('accounting.fixed_assets.classes.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
