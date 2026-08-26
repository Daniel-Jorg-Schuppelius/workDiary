{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Spesenkategorien'))
@section('nav-title', __('Spesenkategorien'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Spesen-Kategorien für Belegerfassung verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.expense-categories.create')"
                    show-label>{{ __('Kategorie anlegen') }}</x-icon-btn>
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('Was sind Spesenkategorien?') }}</h3>
            <div class="text-sm">
                {{ __('Spesenkategorien strukturieren die von Mitarbeitenden erfassten Auslagen (z. B. Verpflegung, Übernachtung, Bewirtung). Sie legen Standardwerte für Steuersatz, Belegpflicht und ob Kosten standardmäßig an Kund:innen weiterberechnet werden fest und steuern Symbol und Farbe in Listen und Auswertungen.') }}
            </div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('admin.expense-categories.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="sort" default class="w-12">#</x-table.th>
                <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                <x-table.th sort="slug">{{ __('Slug') }}</x-table.th>
                <x-table.th sort="tax" align="right">{{ __('MwSt.') }}</x-table.th>
                <th>{{ __('Flags') }}</th>
                <x-table.th sort="expenses" align="center">{{ __('Belege') }}</x-table.th>
                <x-table.th sort="is_active" align="center">{{ __('Aktiv') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($categories as $cat)
            <tr>
                <td class="text-muted">{{ $cat->sort }}</td>
                <td class="font-medium">
                    <span class="inline-flex items-center gap-2">
                        <x-icon :name="$cat->icon ?: 'receipt_long'" class="text-{{ $cat->color ?: 'primary' }}" />
                        {{ $cat->label }}
                    </span>
                    @if ($cat->description)
                        <div class="text-xs text-muted">{{ $cat->description }}</div>
                    @endif
                </td>
                <td class="font-mono text-sm text-muted">{{ $cat->slug }}</td>
                <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((float) ($cat->default_tax_rate?->getNumericValue() ?? '0')), 2, withThousandsSeparator: true) }} %</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @if ($cat->default_billable) <x-status-badge size="xs" tone="info">{{ __('Berechenbar') }}</x-status-badge> @endif
                        @if ($cat->requires_receipt) <x-status-badge size="xs" tone="warning">{{ __('Belegpflicht') }}</x-status-badge> @endif
                    </div>
                </td>
                <td class="text-center">{{ $cat->expenses_count ?? 0 }}</td>
                <td class="text-center">
                    @if ($cat->is_active)
                        <x-status-badge tone="success">{{ __('Ja') }}</x-status-badge>
                    @else
                        <x-status-badge tone="error">{{ __('Nein') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.expense-categories.edit', $cat)"
                                    :label="__('Bearbeiten')" />
                        <x-action-form :action="route('admin.expense-categories.destroy', $cat)" method="DELETE"
                              :confirm="__('Kategorie wirklich löschen?')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="receipt_long" :colspan="8" :title="__('Keine Spesenkategorien vorhanden')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$categories" standing />
</x-index-page>
@endsection
