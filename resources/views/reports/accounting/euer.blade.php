{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : euer.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  EÜR-Vorschau (Feature 125, MVP-676/680) nach Zufluss/Abfluss, gegliedert nach
  den Zeilen der Anlage EÜR. Ungeklärte Fälle stehen sichtbar dabei —
  weggerechnet wären sie eine falsche Sicherheit.
--}}

@extends('layouts.app')

@section('title', __('accounting.reports.card.euer.title'))
@section('nav-title', __('accounting.reports.card.euer.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.reports.period', ['from' => $from->fdate(), 'to' => $to->fdate()])">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.euer', ['export' => 'csv'])" :label="__('CSV')" />
            <x-icon-btn icon="table_view" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.euer', ['export' => 'xlsx'])" :label="__('Excel')" />
            <x-icon-btn icon="picture_as_pdf" size="sm" tone="ghost" show-label
                        :href="route('reports.accounting.euer', ['export' => 'pdf'])" :label="__('PDF')" />
        </x-slot:actions>

        <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.reports.euer_preview_hint') }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('accounting.reports.section.income')" :value="$income" />
            <x-kpi-tile :label="__('accounting.reports.section.expense')" :value="$expense" />
            <x-kpi-tile :label="__('accounting.reports.column.result')" :value="$result" />
            <x-kpi-tile :label="__('accounting.reports.column.not_deductible')" :value="$not_deductible" />
        </div>

        <x-card :title="__('accounting.reports.unclear.title')" icon="help">
            @if ($unclear === [])
                <p class="text-sm text-base-content/60">{{ __('accounting.reports.unclear.none') }}</p>
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
                    <th>{{ __('accounting.reports.column.euer_category') }}</th>
                    <th class="text-right">{{ __('accounting.reports.column.gross') }}</th>
                    <th class="text-right">{{ __('accounting.reports.column.deductible') }}</th>
                    <th class="text-right">{{ __('accounting.reports.column.not_deductible') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td>
                        <x-status-badge :tone="$row['category']->tone()">{{ $row['category']->label() }}</x-status-badge>
                        @if ($row['manual'])
                            <span class="ml-2 text-xs text-base-content/60">{{ __('accounting.reports.euer_manual_hint') }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ $row['gross'] }}</td>
                    <td class="text-right font-mono">{{ $row['deductible'] }}</td>
                    <td class="text-right font-mono">{{ $row['not_deductible'] }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="summarize" :title="__('accounting.reports.empty')" compact />
            @endforelse
        </x-table>

    </x-index-page>
@endsection
