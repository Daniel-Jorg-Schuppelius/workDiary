{{--
  Created on   : Fri Aug 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-account-comparison.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeitkonten-Periodenvergleich (MVP-540): Umsätze je KW/Monat nebeneinander.
--}}

@extends('layouts.app')
@section('title', __('Zeitkonten-Periodenvergleich'))
@section('nav-title', __('Periodenvergleich'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Konto-Umsätze je Kalenderwoche oder Monat nebeneinander; Excel exportiert alle Konten als je ein Arbeitsblatt.')">
            <x-slot:actions>
                @if ($account !== null)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="request()->fullUrlWithQuery(['export' => 'csv'])" show-label>CSV</x-icon-btn>
                    <x-icon-btn icon="table_view" tone="outline" size="sm"
                                :href="request()->fullUrlWithQuery(['export' => 'xlsx'])" show-label>Excel</x-icon-btn>
                    <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                                :href="request()->fullUrlWithQuery(['export' => 'pdf'])" show-label>PDF</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($account === null)
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">account_balance</span>'
            :title="__('Keine Zeitkonten eingerichtet')" />
    @else
        <x-filter-bar :action="route('reports.time-account-comparison')" :reset="route('reports.time-account-comparison')">
            <x-filter-field :label="__('Konto')" for="tac-account">
                <select id="tac-account" name="account" class="select select-sm select-bordered" data-autosubmit>
                    @foreach ($accounts as $candidate)
                        <option value="{{ \App\Support\Sqid::encode(\App\Models\TimeAccount::class, (int) $candidate->id) }}"
                                @selected((int) $candidate->id === (int) $account->id)>{{ $candidate->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('Raster')" for="tac-granularity">
                <select id="tac-granularity" name="granularity" class="select select-sm select-bordered" data-autosubmit>
                    <option value="week" @selected($granularity === 'week')>{{ __('Kalenderwoche') }}</option>
                    <option value="month" @selected($granularity === 'month')>{{ __('Monat') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-card>
            @if (empty($rows))
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">account_balance</span>'
                               :title="__('Keine Buchungen im gewählten Zeitraum.')" />
            @else
                <div class="overflow-x-auto">
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('Mitarbeiter') }}</x-table.th>
                                <x-table.th align="right">{{ __('Anfangsstand') }}</x-table.th>
                                @foreach ($periods as $period)
                                    <x-table.th align="right">{{ $period['label'] }}</x-table.th>
                                @endforeach
                                <x-table.th align="right">{{ __('Umsatz') }}</x-table.th>
                                <x-table.th align="right">{{ __('Endstand') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap">{{ $row['user']->name }}</td>
                                <td class="text-right tabular-nums">{{ $account->unit->format($row['opening']) }}</td>
                                @foreach ($periods as $period)
                                    <td class="text-right tabular-nums">
                                        @if (($row['byPeriod'][$period['key']] ?? 0.0) !== 0.0)
                                            {{ $account->unit->format($row['byPeriod'][$period['key']]) }}
                                        @else
                                            <span class="opacity-40">–</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-right tabular-nums">{{ $account->unit->format($row['turnover']) }}</td>
                                <td class="text-right">
                                    <x-status-badge :tone="$row['tone']" size="sm">
                                        <span class="tabular-nums">{{ $account->unit->format($row['closing']) }}</span>
                                    </x-status-badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
