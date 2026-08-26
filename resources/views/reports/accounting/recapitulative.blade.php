{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recapitulative.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zusammenfassende Meldung (Feature 125, MVP-687). Die Frist ist der 25. Tag
  nach dem Meldezeitraum — die Dauerfristverlängerung gilt hier ausdrücklich
  nicht. Umsätze ohne USt-IdNr. stehen als Klärungsfall dabei.
--}}

@extends('layouts.app')

@section('title', __('accounting.recapitulative.title'))
@section('nav-title', __('accounting.recapitulative.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="$period?->label() ?? __('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.recapitulative', ['export' => 'csv', 'period' => $period?->key])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.recapitulative', ['export' => 'xlsx', 'period' => $period?->key])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.recapitulative', ['export' => 'pdf', 'period' => $period?->key])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <div>
                <span>{{ __('accounting.recapitulative.hint') }}</span>
                @if ($due_on)
                    <div class="mt-1 text-xs">
                        {{ __('accounting.recapitulative.due', ['date' => $due_on->fdate()]) }}
                    </div>
                @endif
            </div>
        </div>

        @if ($period !== null && $periods !== [])
            <x-filter-bar :action="route('reports.accounting.recapitulative')" :reset="route('reports.accounting.recapitulative')">
                <select name="period" class="select select-sm select-bordered w-56 shrink-0"
                        aria-label="{{ __('accounting.filing.field.period') }}">
                    @foreach ($periods as $option)
                        <option value="{{ $option->key }}" @selected($option->key === $period->key)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-muted">{{ __('accounting.recapitulative.interval', ['interval' => $interval->label()]) }}</span>
            </x-filter-bar>
        @endif

        <div class="grid gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('accounting.recapitulative.total')" :value="$total" />
            <x-kpi-tile :label="__('accounting.reports.kpi.findings')" :value="count($unclear)" />
        </div>

        <x-card :title="__('accounting.reports.unclear.title')" icon="help">
            @if ($unclear === [])
                <p class="text-sm text-muted">{{ __('accounting.reports.unclear.none') }}</p>
            @else
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($unclear as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.recapitulative.column.vat_id') }}</th>
                    <th>{{ __('accounting.ledger.column.name') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="font-mono">{{ $row['vat_id'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-right font-mono">{{ $row['amount'] }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="3" icon="public" :title="__('accounting.reports.empty')" compact />
            @endforelse
        </x-table>

    </x-index-page>
@endsection
