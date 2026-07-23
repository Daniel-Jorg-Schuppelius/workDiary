{{--
  Created on   : Sun Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : drilldown.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Beleg-Drilldown einer Scorecard-Kennzahl (Bauturbo Welle D): Quellbelege
     (Wareneingänge/Bestellungen, Reklamationen, Preishistorie) — erreichbar nur
     über signierten, kurzlebigen Link. --}}

@extends('layouts.app')
@section('title', $title . ' — ' . $supplier->name)
@section('nav-title', __('scorecard.title'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $title }} — {{ $supplier->name }}</x-slot:title>
            <x-slot:subtitle>{{ $from->toDateString() }} – {{ $to->toDateString() }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($rows->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :title="__('scorecard.no_data')" />
    @else
        <x-card padding="p-0">
            <x-table bare>
                @if ($kind === 'deliveries')
                    <x-slot:head>
                        <tr>
                            <th>{{ __('scorecard.col_order') }}</th>
                            <th>{{ __('scorecard.col_expected') }}</th>
                            <th>{{ __('scorecard.col_delivered') }}</th>
                            <th>{{ __('scorecard.col_ontime_flag') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $row)
                        <tr class="hover">
                            <td class="tabular-nums">{{ $row['number'] }}</td>
                            <td class="tabular-nums">{{ $row['expected_at'] }}</td>
                            <td class="tabular-nums">{{ $row['delivered_at'] ?? '—' }}</td>
                            <td>
                                @if ($row['delivered_at'] === null)
                                    <x-status-badge tone="ghost" size="sm">{{ __('scorecard.pending') }}</x-status-badge>
                                @elseif ($row['on_time'])
                                    <x-status-badge tone="success" size="sm">{{ __('scorecard.on_time') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="error" size="sm">{{ __('scorecard.late') }}</x-status-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @elseif ($kind === 'claims')
                    <x-slot:head>
                        <tr>
                            <th>{{ __('scorecard.col_claim') }}</th>
                            <th>{{ __('scorecard.col_title') }}</th>
                            <th>{{ __('scorecard.col_reported') }}</th>
                            <th>{{ __('scorecard.col_status') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $claim)
                        <tr class="hover">
                            <td class="tabular-nums">{{ $claim->number }}</td>
                            <td>{{ $claim->title }}</td>
                            <td class="tabular-nums">{{ $claim->reported_at->toDateString() }}</td>
                            <td>{{ $claim->status->label() }}</td>
                        </tr>
                    @endforeach
                @else
                    <x-slot:head>
                        <tr>
                            <th>{{ __('scorecard.col_order') }}</th>
                            <th>{{ __('scorecard.col_ordered_at') }}</th>
                            <th>{{ __('scorecard.col_article') }}</th>
                            <th class="text-right">{{ __('scorecard.col_unit_price') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $line)
                        <tr class="hover">
                            <td class="tabular-nums">{{ $line->purchaseOrder?->number }}</td>
                            <td class="tabular-nums">{{ $line->purchaseOrder?->ordered_at?->toDateString() }}</td>
                            <td>{{ $line->article?->name ?? $line->description }}</td>
                            <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line->unit_price, 2, withThousandsSeparator: true) }} {{ $line->currency->value }}</td>
                        </tr>
                    @endforeach
                @endif
            </x-table>
        </x-card>

        <x-pagination :paginator="$rows" standing />
    @endif
</x-page-shell>
@endsection
