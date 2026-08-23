{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zählerstands-Faktura (Feature 116, MVP-605): Vereinbarungen und — wichtiger —
  die übersprungenen Läufe, denn eine fehlende Ablesung ist die eigentliche
  Arbeit.
--}}

@extends('layouts.app')

@section('title', __('metering.title'))
@section('nav-title', __('metering.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('metering.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('metering.create')"
                        show-label>{{ __('metering.action.create') }}</x-icon-btn>
        </x-slot:actions>

        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('metering.draft_notice') }}</span>
        </div>

        @if ($skipped->isNotEmpty())
            <div class="rounded-box border border-warning/40 bg-warning/5 px-4 py-3">
                <p class="text-sm font-medium">{{ __('metering.skipped.heading') }}</p>
                <p class="mt-1 text-xs text-base-content/70">{{ __('metering.skipped.hint') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($skipped as $run)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $run->agreement?->asset?->name ?? '—' }}</span>
                            <span class="text-base-content/60">{{ $run->agreement?->customer?->displayLabel() ?? '—' }}</span>
                            <span class="text-base-content/60 tabular-nums">{{ $run->period_start->fdate() }} – {{ $run->period_end->fdate() }}</span>
                            <x-status-badge tone="warning" outline>{{ __('metering.skipped.reason.' . $run->skipped_reason) }}</x-status-badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('metering.column.title') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('metering.column.customer') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('metering.column.asset') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('metering.column.base_price') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('metering.column.unit_price') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('metering.column.free_units') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('metering.column.next_run_on') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('metering.column.status') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($agreements as $agreement)
                <tr class="hover">
                    <td class="font-medium">{{ $agreement->title }}</td>
                    <td>{{ $agreement->customer?->displayLabel() ?? '—' }}</td>
                    <td>{{ $agreement->asset?->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $agreement->base_price, 2, withThousandsSeparator: true) }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $agreement->unit_price, 4) }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $agreement->free_units, 2) }}</td>
                    <td class="whitespace-nowrap">{{ $agreement->next_run_on->fdate() }}</td>
                    <td>{{ __('metering.status.' . $agreement->status) }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('metering.edit', $agreement)"
                                        :label="__('Bearbeiten')" />
                            <x-action-form :action="route('metering.run', $agreement)">
                                <x-icon-btn icon="receipt_long" size="xs" tone="ghost" type="submit"
                                            :label="__('metering.action.run')" />
                            </x-action-form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9" icon="speed" :title="__('metering.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$agreements" standing />
    </x-index-page>
@endsection
