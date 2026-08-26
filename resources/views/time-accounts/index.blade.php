{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eigene Zeitkonten (MVP-526): Stand + Ampel + Trend, Journal-Drilldown.
--}}

@extends('layouts.app')
@section('title', __('Meine Zeitkonten'))
@section('nav-title', __('Zeitkonten'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Zusatzkonten mit nachvollziehbarem Journal — Gleitzeit und Urlaub findest du im Arbeitszeitkonto.')" />
    </x-slot:toolbar>

    @if (empty($rows))
        <x-empty-state framed
            icon="account_balance"
            :title="__('Keine Zeitkonten eingerichtet')"
            :message="__('Ihre Organisation nutzt derzeit keine Zusatz-Zeitkonten.')" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($rows as $row)
                <x-card>
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold">{{ $row['account']->name }}</h3>
                        <span class="ml-auto"></span>
                        <x-status-badge :tone="$row['tone']" size="sm">
                            <span class="tabular-nums">{{ $row['account']->unit->format($row['balance']) }}</span>
                        </x-status-badge>
                    </div>
                    <p class="text-sm text-muted mt-1">
                        {{ __('Ø-Umsatz/Monat') }}: <span class="tabular-nums">{{ $row['account']->unit->format($row['avg_turnover']) }}</span>
                        · {{ __('Trend (+3 Monate)') }}: <span class="tabular-nums">{{ $row['account']->unit->format($row['projected']) }}</span>
                    </p>
                    <div class="mt-2">
                        <a class="link link-hover text-sm" href="{{ route('time-accounts.index', ['account' => $row['sqid']]) }}">
                            {{ __('Journal ansehen') }}
                        </a>
                    </div>
                </x-card>
            @endforeach
        </div>

        @if ($detail !== null && $entries !== null)
            <x-card>
                <h3 class="font-semibold mb-2">{{ __('Journal') }} — {{ $detail->name }}</h3>
                @if ($entries->isEmpty())
                    <p class="text-muted">{{ __('Noch keine Buchungen.') }}</p>
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('Datum') }}</x-table.th>
                                <x-table.th align="right">{{ __('Menge') }}</x-table.th>
                                <x-table.th>{{ __('Quelle') }}</x-table.th>
                                <x-table.th>{{ __('Anmerkung') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($entries as $entry)
                            <tr class="{{ $entry->reversal_of_id !== null ? 'opacity-60' : '' }}">
                                <td class="tabular-nums">{{ $entry->booking_date->fdate() }}</td>
                                <td class="text-right tabular-nums {{ (float) $entry->quantity < 0 ? 'text-error' : '' }}">
                                    {{ $detail->unit->format((float) $entry->quantity) }}
                                </td>
                                <td class="text-sm text-muted">
                                    @if ($entry->reversal_of_id !== null)
                                        {{ __('Storno') }}
                                    @elseif ($entry->source_type === null)
                                        {{ __('Sonderbuchung') }}
                                    @else
                                        {{ $entry->source_type }}
                                    @endif
                                </td>
                                <td class="text-sm">{{ $entry->note }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                    <x-pagination :paginator="$entries" standing />
                @endif
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
