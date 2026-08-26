{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : quality.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungsqualität (Feature 125, MVP-676): was der Auswertung im Weg steht.
  Der Bericht ist bewusst eine Arbeitsliste, keine Statistik.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.quality.title'))
@section('nav-title', __('accounting.reports.card.quality.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.quality', ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.quality', ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.quality', ['export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('accounting.reports.quality.kpi.drafts')" :value="$drafts" />
            <x-kpi-tile :label="__('accounting.reports.quality.kpi.unbalanced')" :value="$unbalanced" />
            <x-kpi-tile :label="__('accounting.reports.quality.kpi.blocked_runs')" :value="$blocked_runs" />
            <x-kpi-tile :label="__('accounting.reports.quality.kpi.open_expectations')" :value="$open_expectations" />
        </div>

        <x-card :title="__('accounting.reports.quality.headline')" icon="fact_check">
            @if ($findings === [])
                <p class="text-sm text-muted">{{ __('accounting.reports.quality.none') }}</p>
            @else
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($findings as $finding)
                        <li>{{ $finding }}</li>
                    @endforeach
                </ul>
                <div class="mt-3 flex gap-2">
                    <x-icon-btn icon="inbox" size="sm" tone="ghost" show-label
                                :href="route('finance.accounting.inbox.index')"
                                :label="__('accounting.inbox.menu')" />
                    <x-icon-btn icon="event_repeat" size="sm" tone="ghost" show-label
                                :href="route('finance.accounting.recurring.index')"
                                :label="__('accounting.recurring.menu')" />
                </div>
            @endif
        </x-card>
    </x-index-page>
@endsection
