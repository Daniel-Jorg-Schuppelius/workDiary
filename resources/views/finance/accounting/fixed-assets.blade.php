{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : fixed-assets.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlagenregister (Feature 133, MVP-698): Voll-Höhe-Tabelle Nummer/
  Bezeichnung/Anschaffung/AK/Nutzungsdauer/Restbuchwert/Status. Die AfA
  wird nicht hier gebucht, sondern als Vorschlag über die Inbox.
--}}

@extends('layouts.app')

@section('title', __('accounting.fixed_assets.title'))
@section('nav-title', __('accounting.fixed_assets.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.fixed_assets.subtitle')">
        <x-slot:actions>
            @if ($canConfigure)
                <x-icon-btn icon="add" size="sm" tone="primary"
                            data-entry-modal-trigger
                            :href="route('finance.accounting.fixed-assets.create')"
                            :label="__('accounting.fixed_assets.action.add')" />
            @endif
        </x-slot:actions>

        <x-accounting.sovereignty-note />

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('accounting.fixed_assets.kpi.active')" :value="$activeCount" />
            <x-kpi-tile :label="__('accounting.fixed_assets.kpi.total')" :value="$assets->count()" />
            <x-kpi-tile :label="__('accounting.fixed_assets.kpi.book_value_year', ['year' => $currentYear])"
                        :value="$bookValueTotal->format()" format="raw" />
        </div>

        <x-filter-bar :action="route('finance.accounting.fixed-assets.index')" :reset="route('finance.accounting.fixed-assets.index')">
            <select name="status" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit
                    aria-label="{{ __('accounting.ledger.column.status') }}">
                <option value="">{{ __('accounting.fixed_assets.filter.all') }}</option>
                @foreach (\App\Enums\Finance\FixedAssetStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.fixed_assets.column.no') }}</th>
                    <th>{{ __('accounting.fixed_assets.column.name') }}</th>
                    <th>{{ __('accounting.fixed_assets.column.acquired_on') }}</th>
                    <th class="text-right">{{ __('accounting.fixed_assets.column.cost') }}</th>
                    <th class="text-center">{{ __('accounting.fixed_assets.column.useful_life') }}</th>
                    <th class="text-right">{{ __('accounting.fixed_assets.column.book_value', ['year' => $currentYear]) }}</th>
                    <th>{{ __('accounting.ledger.column.status') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($assets as $asset)
                <tr class="hover {{ $asset->isDisposed() ? 'opacity-60' : '' }}">
                    <td class="font-mono text-sm">{{ $asset->displayNo() }}</td>
                    <td class="font-medium">
                        {{ $asset->name }}
                        @if ($asset->asset)
                            <div class="text-xs text-muted">{{ $asset->asset->asset_no }} · {{ $asset->asset->name }}</div>
                        @endif
                    </td>
                    <td>{{ $asset->acquired_on->fdate() }}</td>
                    <td class="text-right font-mono">{{ $asset->acquisition_cost?->format() }}</td>
                    <td class="text-center">{{ __('accounting.fixed_assets.months', ['count' => $asset->useful_life_months]) }}</td>
                    <td class="text-right font-mono">{{ ($bookValues[$asset->id] ?? null)?->format() ?? '—' }}</td>
                    <td>
                        <x-status-badge :tone="$asset->status->tone()">{{ $asset->status->label() }}</x-status-badge>
                        @if ($asset->disposed_on)
                            <div class="text-xs text-muted">{{ $asset->disposed_on->fdate() }}</div>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                        :href="route('finance.accounting.fixed-assets.show', $asset)"
                                        :label="__('Anzeigen')" />
                            @if ($canConfigure && ! $asset->isDisposed())
                                <x-icon-btn icon="edit" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('finance.accounting.fixed-assets.edit', $asset)"
                                            :label="__('Bearbeiten')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8" icon="precision_manufacturing" :title="__('accounting.fixed_assets.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
