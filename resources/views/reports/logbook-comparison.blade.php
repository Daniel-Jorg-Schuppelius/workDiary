{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : logbook-comparison.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  1-%-Vergleich (MVP-993): Fahrtenbuchmethode gegen 1-%-Regel je Fahrzeug und Jahr.
  Variablen: $year, $rows (list<array{vehicle: Vehicle, result: array}>)
--}}
@extends('layouts.app')
@section('title', __('1-%-Vergleich'))
@section('nav-title', __('1-%-Vergleich'))
@include('partials.page-fill')
@section('content')
<x-index-page overflow="clip" :subtitle="__('Geldwerter Vorteil je Fahrzeug: Fahrtenbuchmethode gegen 1-%-Regel. Vereinfachte Rechnung, keine Steuerberatung.')"
              back-route="reports.logbook" :back-label="__('Fahrtenbuch-Nachweis')">
    <x-filter-bar :action="route('reports.logbook-comparison')" :reset="route('reports.logbook-comparison')">
        <x-filter-field :label="__('Jahr')" for="cmp-year">
            <select id="cmp-year" name="year" class="select select-sm select-bordered" data-autosubmit>
                @for ($y = now()->year; $y >= now()->year - 6; $y--)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endfor
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('Fahrzeug') }}</th>
                <th class="text-right">{{ __('Monate') }}</th>
                <th class="text-right">{{ __('km gesamt') }}</th>
                <th class="text-right">{{ __('davon privat') }}</th>
                <th class="text-right">{{ __('davon Arbeitsweg') }}</th>
                <th class="text-right">{{ __('Kosten gesamt') }}</th>
                <th class="text-right">{{ __('Fahrtenbuchmethode') }}</th>
                <th class="text-right">{{ __('1-%-Regel') }}</th>
                <th>{{ __('Günstiger') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            @php $r = $row['result']; @endphp
            <tr class="hover">
                <td class="font-medium">{{ $row['vehicle']->displayName() }}</td>
                <td class="text-right">{{ $r['months'] }}</td>
                <td class="text-right font-mono">{{ $r['km_total'] }}</td>
                <td class="text-right font-mono">{{ $r['km_private'] }}</td>
                <td class="text-right font-mono">{{ $r['km_commute'] }}</td>
                <td class="text-right font-mono" title="{{ __('Energie') }} {{ $r['energy']->format() }} · {{ __('Sonstige') }} {{ $r['other']->format() }}">{{ $r['total_costs']->format() }}</td>
                <td class="text-right font-mono">{{ $r['logbook']->format() }}</td>
                <td class="text-right font-mono">
                    @if ($r['one_percent'] !== null)
                        {{ $r['one_percent']->format() }}
                        @if ($r['factor'] !== '1')
                            <span class="block text-xs text-muted">{{ __('Bemessung :factor', ['factor' => \CommonToolkit\ValueObjects\Percentage::of($r['factor'])->format()]) }}</span>
                        @endif
                    @else
                        <span class="text-xs text-muted">{{ __('Listenpreis fehlt') }}</span>
                    @endif
                </td>
                <td>
                    @if ($r['cheaper'] === 'logbook')
                        <x-status-badge tone="success" size="sm">{{ __('Fahrtenbuchmethode') }}</x-status-badge>
                    @elseif ($r['cheaper'] === 'one_percent')
                        <x-status-badge tone="info" size="sm">{{ __('1-%-Regel') }}</x-status-badge>
                    @else
                        –
                    @endif
                </td>
                <td class="text-right">
                    @can('update', $row['vehicle'])
                        <x-icon-btn icon="euro" tone="outline" size="xs" data-entry-modal-trigger
                                    :href="route('reports.logbook-comparison.costs-form', ['vehicle' => $row['vehicle'], 'year' => $year])"
                                    :label="__('Sonstige Jahreskosten')" />
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty icon="directions_car" :colspan="10" :title="__('Keine Fahrzeuge im Fahrtenbuch-Modus.')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
