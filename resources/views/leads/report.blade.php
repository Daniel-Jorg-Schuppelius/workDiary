{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lead-Auswertung nach Quelle und Konversion (MVP-1296). --}}
@extends('layouts.app')
@section('title', __('lead.report.title'))
@section('nav-title', __('lead.report.title'))

@section('content')
<x-index-page :subtitle="__('lead.report.subtitle')">
    <x-slot:actions>
        <x-report-export :url="fn (string $format) => route('leads.report', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => $format])" :formats="['csv', 'xlsx']" tone="ghost" />
    </x-slot:actions>

    <x-filter-bar :action="route('leads.report')" :reset="route('leads.report')">
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      :from="$from->toDateString()" :to="$to->toDateString()"
                      :from-label="__('von')" :to-label="__('bis')" />
    </x-filter-bar>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        <x-card><div class="text-xs text-muted">{{ __('lead.report.total') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['total'] }}</div></x-card>
        <x-card><div class="text-xs text-muted">{{ __('lead.report.converted') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['converted'] }}</div></x-card>
        <x-card><div class="text-xs text-muted">{{ __('lead.report.rate') }}</div><div class="text-2xl font-semibold tabular-nums">{{ $totals['rate'] !== null ? $totals['rate'] . ' %' : '—' }}</div></x-card>
    </div>

    <x-table>
        <x-slot:head>
            <th>{{ __('Quelle') }}</th>
            <th class="text-right">{{ __('lead.report.total') }}</th>
            <th class="text-right">{{ __('lead.report.open') }}</th>
            <th class="text-right">{{ __('lead.report.converted') }}</th>
            <th class="text-right">{{ __('lead.report.discarded') }}</th>
            <th class="text-right">{{ __('lead.report.rate') }}</th>
            <th class="text-right">{{ __('lead.report.avgDays') }}</th>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr>
                <td><a class="link" href="{{ route('leads.index', ['source' => $row['source']->value]) }}">{{ $row['source']->label() }}</a></td>
                <td class="text-right tabular-nums">{{ $row['total'] }}</td>
                <td class="text-right tabular-nums">{{ $row['open'] }}</td>
                <td class="text-right tabular-nums">{{ $row['converted'] }}</td>
                <td class="text-right tabular-nums">{{ $row['discarded'] }}</td>
                <td class="text-right tabular-nums">{{ $row['rate'] }} %</td>
                <td class="text-right tabular-nums">{{ $row['avg_days'] ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="7" compact :title="__('lead.report.empty')" />
        @endforelse
    </x-table>
</x-index-page>
@endsection
