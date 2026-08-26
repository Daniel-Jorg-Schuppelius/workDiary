{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : logbook.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Fahrtenbuch-Nachweis'))
@section('nav-title', __('Fahrtenbuch-Nachweis'))

@section('content')
@php
    $num = fn (float|int $v, int $d = 0) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, withThousandsSeparator: true);
    $linkParams = array_filter(['vehicle' => $vehicle?->sqid, 'from' => $from, 'to' => $to]);
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Steuerliches Fahrtenbuch je Fahrzeug: km-Stände, Fahrtart, Ziel, Zweck, Fahrer — Summen je Fahrtart und privater Anteil.')">
            <x-slot:actions>
                @if ($vehicle)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="route('reports.logbook', array_merge($linkParams, ['export' => 'csv']))"
                                show-label>CSV</x-icon-btn>
                    <x-icon-btn icon="table_view" tone="outline" size="sm"
                                :href="route('reports.logbook', array_merge($linkParams, ['export' => 'xlsx']))"
                                show-label>Excel</x-icon-btn>
                    <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                                :href="route('reports.logbook', array_merge($linkParams, ['export' => 'pdf']))"
                                show-label>PDF</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.logbook')" :reset="route('reports.logbook')">
        <x-filter-field :label="__('Fahrzeug')" for="lb-vehicle">
            <select id="lb-vehicle" name="vehicle" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('— Fahrzeug wählen —') }}</option>
                @foreach ($vehicles as $v)
                    <option value="{{ $v->sqid }}" @selected($vehicle && (int) $vehicle->id === (int) $v->id)>
                        {{ $v->displayName() }}{{ $v->logbook_mode ? ' · ' . __('Fahrtenbuch-Modus') : '' }}
                    </option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if (! $vehicle)
        <x-card>
            <x-empty-state icon="menu_book" :title="__('Bitte ein Fahrzeug wählen.')" />
        </x-card>
    @else
        @unless ($vehicle->logbook_mode)
            <div class="alert alert-warning text-sm" role="status">{{ __('Dieses Fahrzeug ist nicht im Fahrtenbuch-Modus — Fahrten ohne km-Stände und ohne Festschreibung sind steuerlich kein Fahrtenbuch.') }}</div>
        @endunless

        <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
            <x-kpi-tile :label="__('Fahrten')" :value="$totals['trips']" :hint="$totals['locked'] . ' ' . __('festgeschrieben')" />
            <x-kpi-tile :label="__('Σ km')" :value="$num($totals['km']) . ' km'" />
            @foreach (\App\Enums\Travel\TripKind::cases() as $kind)
                <x-kpi-tile :label="$kind->label()" :value="$num($totals['by_kind'][$kind->value]) . ' km'" />
            @endforeach
            <x-kpi-tile :label="__('Privater Anteil')" :value="$totals['private_share'] !== null ? $num($totals['private_share'], 1) . ' %' : '–'" />
        </div>

        <x-card>
            @if (empty($rows))
                <x-empty-state icon="menu_book" :title="__('Keine Fahrten im gewählten Zeitraum.')" />
            @else
                <x-table table-sort="client" bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('Datum') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('Start-km') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('End-km') }}</x-table.th>
                            <x-table.th sort type="number" align="right">{{ __('km') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Fahrtart') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Ziel') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Zweck') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Fahrer') }}</x-table.th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </x-slot:head>
                    <x-slot:foot>
                        <tr class="font-bold">
                            <td colspan="3">{{ __('Gesamt') }}</td>
                            <td class="text-right tabular-nums">{{ $num($totals['km']) }}</td>
                            <td colspan="5">
                                @foreach (\App\Enums\Travel\TripKind::cases() as $kind)
                                    <span class="mr-3">{{ $kind->label() }}: {{ $num($totals['by_kind'][$kind->value]) }} km</span>
                                @endforeach
                            </td>
                        </tr>
                    </x-slot:foot>
                    @foreach ($rows as $r)
                        @php($log = $r['log'])
                        <tr class="{{ $r['superseded'] ? 'line-through opacity-60' : '' }}">
                            <td data-sort-value="{{ $log->date?->toDateString() }}">{{ $log->date?->fdate() }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $log->odometer_start_km ?? -1 }}">{{ $log->odometer_start_km !== null ? $num($log->odometer_start_km) : '–' }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ $log->odometer_end_km ?? -1 }}">{{ $log->odometer_end_km !== null ? $num($log->odometer_end_km) : '–' }}</td>
                            <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['km'] }}">{{ $num((int) $r['km']) }}</td>
                            <td><x-status-badge tone="ghost" size="sm">{{ $log->trip_kind->label() }}</x-status-badge></td>
                            <td class="max-w-xs truncate">{{ $log->to_address }}</td>
                            <td class="max-w-xs truncate">{{ $log->purpose }}</td>
                            <td>{{ $log->user?->name ?? '–' }}</td>
                            <td class="whitespace-nowrap">
                                @if ($r['superseded'])
                                    <x-status-badge tone="error" size="xs">{{ __('storniert') }}</x-status-badge>
                                @elseif ($log->isLocked())
                                    <x-status-badge tone="success" size="xs" :title="$log->locked_at?->fdatetime()">{{ __('festgeschrieben') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="warning" size="xs">{{ __('offen') }}</x-status-badge>
                                @endif
                                @if ($log->isCorrection())
                                    <x-status-badge tone="info" size="xs" :title="$log->correction_reason">{{ __('Stornofahrt') }}</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
</x-page-shell>
@endsection
