{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-accounts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeitkonten-Auswertung (MVP-526): Anfangsstand / Umsatz / Endstand.
--}}

@extends('layouts.app')
@section('title', __('Zeitkonten (Auswertung)'))
@section('nav-title', __('Zeitkonten-Auswertung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Anfangsstand, Umsatz und Endstand je Mitarbeiter im gewählten Zeitraum.')">
            <x-slot:actions>
                @if ($account !== null)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="request()->fullUrlWithQuery(['export' => 'csv'])" show-label>CSV</x-icon-btn>
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
        <x-filter-bar :action="route('reports.time-accounts')" :reset="route('reports.time-accounts')">
            <x-filter-field :label="__('Konto')" for="ta-account">
                <select id="ta-account" name="account" class="select select-sm select-bordered" data-autosubmit>
                    @foreach ($accounts as $candidate)
                        <option value="{{ \App\Support\Sqid::encode(\App\Models\TimeAccount::class, (int) $candidate->id) }}"
                                @selected((int) $candidate->id === (int) $account->id)>{{ $candidate->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-card>
            @if (empty($rows))
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">account_balance</span>'
                               :title="__('Keine Buchungen im gewählten Zeitraum.')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th align="right">{{ __('Anfangsstand') }}</x-table.th>
                            <x-table.th align="right">{{ __('Umsatz') }}</x-table.th>
                            <x-table.th align="right">{{ __('Endstand') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['user']->name }}</td>
                            <td class="text-right tabular-nums">{{ $account->unit->format($row['opening']) }}</td>
                            <td class="text-right tabular-nums">{{ $account->unit->format($row['turnover']) }}</td>
                            <td class="text-right">
                                <x-status-badge :tone="$row['tone']" size="sm">
                                    <span class="tabular-nums">{{ $account->unit->format($row['closing']) }}</span>
                                </x-status-badge>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
