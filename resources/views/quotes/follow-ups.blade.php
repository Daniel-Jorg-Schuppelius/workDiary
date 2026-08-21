{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : follow-ups.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachfass-Arbeitsliste (Feature 112, MVP-601): fällige und anstehende
  Nachfasstermine, ablaufende Angebote ohne Reaktion und versandte Angebote
  ganz ohne Termin.
--}}

@extends('layouts.app')

@section('title', __('quotes.follow_up.title'))
@section('nav-title', __('quotes.follow_up.title'))

@section('content')
    <x-index-page :subtitle="__('quotes.follow_up.subtitle')">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-kpi-tile :label="__('quotes.follow_up.kpi.due')" :value="$due->count()" format="int"
                        :tone="$due->isNotEmpty() ? 'warning' : 'success'" />
            <x-kpi-tile :label="__('quotes.follow_up.kpi.upcoming')" :value="$upcoming->count()" format="int" />
            <x-kpi-tile :label="__('quotes.follow_up.kpi.expiring', ['days' => $expiringDays])" :value="$expiring->count()" format="int"
                        :tone="$expiring->isNotEmpty() ? 'error' : 'neutral'"
                        :hint="__('quotes.follow_up.kpi.expiring_hint')" />
            <x-kpi-tile :label="__('quotes.follow_up.kpi.untracked')" :value="$untracked->count()" format="int"
                        :tone="$untracked->isNotEmpty() ? 'warning' : 'neutral'"
                        :hint="__('quotes.follow_up.kpi.untracked_hint')" />
        </div>

        <x-filter-bar :action="route('quotes.follow-ups.index')" :reset="$mine ? route('quotes.follow-ups.index') : null">
            <x-filter-toggle name="mine" :checked="$mine" :label="__('quotes.follow_up.filter.mine')" />
        </x-filter-bar>

        @foreach ([
            ['key' => 'due', 'rows' => $due, 'date' => 'follow_up_at', 'tone' => 'warning'],
            ['key' => 'expiring', 'rows' => $expiring, 'date' => 'valid_until', 'tone' => 'error'],
            ['key' => 'upcoming', 'rows' => $upcoming, 'date' => 'follow_up_at', 'tone' => null],
            ['key' => 'untracked', 'rows' => $untracked, 'date' => null, 'tone' => null],
        ] as $section)
            @continue($section['rows']->isEmpty())
            <x-card>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">
                    {{ __('quotes.follow_up.section.' . $section['key']) }}
                </h2>
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('quotes.follow_up.column.number') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('quotes.follow_up.column.customer') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('quotes.follow_up.column.owner') }}</x-table.th>
                            <x-table.th sort type="date">{{ __('quotes.follow_up.column.' . ($section['date'] === 'valid_until' ? 'valid_until' : 'follow_up_at')) }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('quotes.follow_up.column.total') }}</x-table.th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($section['rows'] as $quote)
                        <tr class="hover">
                            <td class="whitespace-nowrap font-medium">
                                <a class="link link-hover" href="{{ route('quotes.show', $quote) }}">{{ $quote->number }}</a>
                            </td>
                            <td>{{ $quote->customer?->displayLabel() ?? '—' }}</td>
                            <td>{{ $quote->followUpUser?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                @php($date = $section['date'] === null ? null : $quote->{$section['date']})
                                @if ($date === null)
                                    —
                                @else
                                    <x-status-badge :tone="$section['tone'] ?? 'neutral'" outline>{{ $date->format(\App\Support\Formats::date()) }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($quote->total?->toFloat() ?? 0.0, 2, withThousandsSeparator: true) }}</td>
                            <td class="text-right">
                                <div class="flex justify-end">
                                    <x-icon-btn icon="phone_forwarded" size="xs" tone="ghost"
                                                data-entry-modal-trigger
                                                :href="route('quotes.follow-ups.dialog', $quote)"
                                                :label="__('quotes.follow_up.action')" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endforeach

        @if ($due->isEmpty() && $upcoming->isEmpty() && $expiring->isEmpty() && $untracked->isEmpty())
            <x-empty-state icon="task_alt" :title="__('quotes.follow_up.empty')" />
        @endif
    </x-index-page>
@endsection
