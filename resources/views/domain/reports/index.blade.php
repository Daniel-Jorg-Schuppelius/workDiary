{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('domain.title.reports') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('domain.title.reports'))

@section('content')
<x-index-page :subtitle="__('domain.reports.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="dns" size="sm" :href="route('domains.index')" show-label>{{ __('domain.title.index') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($corridors as $days => $count)
            <x-kpi-tile :label="__('domain.reports.expiry_corridor', ['days' => $days])" :value="$count" />
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2 mt-4">
        <x-card :title="__('domain.reports.forecast')" padding="p-0">
            <x-table size="sm" bare :caption="__('domain.reports.forecast')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('domain.reports.month') }}</x-table.th>
                        <x-table.th align="right">{{ __('domain.reseller.domains') }}</x-table.th>
                        <x-table.th align="right">{{ __('domain.field.renewal_price') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($forecast as $key => $row)
                    <tr><td class="tabular-nums">{{ explode('|', $key)[0] }}</td>
                        <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['amount'], 2, ',', '.') }} {{ $row['currency'] }}</td></tr>
                @empty
                    <x-table.empty :colspan="3" :title="__('domain.reports.no_forecast')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('domain.reports.coverage')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('domain.reports.accounting_lines')" class="tabular-nums" :value="$coverage['accounting']" />
                <x-detail-grid.row :label="__('domain.reports.invoices')" class="tabular-nums" :value="$coverage['invoices']" />
                <x-detail-grid.row :label="__('domain.metric.sync_issues')" class="tabular-nums" :value="$reconciliation" />
            </x-detail-grid>
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 mt-4">
        <x-card :title="__('domain.metric.unmapped')">
            @if ($unmapped->isEmpty())
                <x-empty-state compact tone="ghost" :title="__('domain.reports.all_mapped')" />
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($unmapped->take(15) as $domain)
                        <li><a href="{{ route('domains.show', $domain) }}" class="link link-hover">{{ $domain->external_domain }}</a></li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="__('domain.metric.risky')">
            @if ($risky->isEmpty())
                <x-empty-state compact tone="ghost" :title="__('domain.reports.no_risk')" />
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($risky->take(15) as $domain)
                        <li><a href="{{ route('domains.show', $domain) }}" class="link link-hover">{{ $domain->external_domain }}</a>
                            <x-status-badge tone="warning" size="sm">{{ $domain->renewal_mode?->label() }}</x-status-badge></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-index-page>
@endsection
