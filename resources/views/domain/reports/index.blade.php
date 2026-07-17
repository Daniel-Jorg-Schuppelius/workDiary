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
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.reports.forecast') }}</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('domain.reports.month') }}</th><th class="text-right">{{ __('domain.reseller.domains') }}</th><th class="text-right">{{ __('domain.field.renewal_price') }}</th></tr></thead>
                        <tbody>
                            @forelse ($forecast as $key => $row)
                                <tr><td class="tabular-nums">{{ explode('|', $key)[0] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['amount'], 2, ',', '.') }} {{ $row['currency'] }}</td></tr>
                            @empty
                                <x-table.empty :colspan="3" :title="__('domain.reports.no_forecast')" compact />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.reports.coverage') }}</h2>
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-base-content/60">{{ __('domain.reports.accounting_lines') }}</dt><dd class="tabular-nums">{{ $coverage['accounting'] }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.reports.invoices') }}</dt><dd class="tabular-nums">{{ $coverage['invoices'] }}</dd>
                    <dt class="text-base-content/60">{{ __('domain.metric.sync_issues') }}</dt><dd class="tabular-nums">{{ $reconciliation }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 mt-4">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.metric.unmapped') }}</h2>
                <ul class="text-sm space-y-1">
                    @forelse ($unmapped->take(15) as $domain)
                        <li><a href="{{ route('domains.show', $domain) }}" class="link link-hover">{{ $domain->external_domain }}</a></li>
                    @empty
                        <li class="text-base-content/60">{{ __('domain.reports.all_mapped') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('domain.metric.risky') }}</h2>
                <ul class="text-sm space-y-1">
                    @forelse ($risky->take(15) as $domain)
                        <li><a href="{{ route('domains.show', $domain) }}" class="link link-hover">{{ $domain->external_domain }}</a>
                            <span class="badge badge-warning badge-sm">{{ $domain->renewal_mode?->label() }}</span></li>
                    @empty
                        <li class="text-base-content/60">{{ __('domain.reports.no_risk') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-index-page>
@endsection
