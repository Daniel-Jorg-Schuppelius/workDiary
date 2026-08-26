{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : product-revenue.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Umsatz je Produkt (Feature 140, MVP-705): Menge/Nettoumsatz/Anteil je
     Artikel aus lokalen Rechnungen; Voll-Höhe-Tabelle als letztes Element. --}}

@extends('layouts.app')
@section('title', __('Umsatz je Produkt'))
@section('nav-title', __('Umsatz je Produkt'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    $eur = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
    $qty = fn (float $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, ((int) round($v * 1000)) % 10 !== 0 ? 3 : 2, withThousandsSeparator: true);
    $linkParams = array_filter(['top_n' => $topN !== 10 ? $topN : null]);
    $withoutShare = $total > 0 ? round($withoutArticle / $total * 100, 1) : null;
@endphp

<x-index-page overflow="clip" :subtitle="__('Menge, Nettoumsatz und Anteil je Artikel aus lokal ausgestellten Rechnungen.') . ' · ' . __('Zeitraum') . ': ' . $label">
    <x-slot:actions>
        <x-icon-btn icon="download" tone="outline" size="sm"
                    :href="route('reports.product-revenue', array_merge($linkParams, ['export' => 'csv']))"
                    show-label>CSV</x-icon-btn>
        <x-icon-btn icon="table_view" tone="outline" size="sm"
                    :href="route('reports.product-revenue', array_merge($linkParams, ['export' => 'xlsx']))"
                    show-label>Excel</x-icon-btn>
        <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                    :href="route('reports.product-revenue', array_merge($linkParams, ['export' => 'pdf']))"
                    show-label>PDF</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('reports.product-revenue')" :reset="route('reports.product-revenue')">
        <x-filter-field :label="__('Top-N im Diagramm')" for="pr-top-n" inline>
            <input id="pr-top-n" type="number" name="top_n" value="{{ $topN }}" min="3" max="50" class="input input-sm input-bordered w-24" />
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('Nettoumsatz gesamt')" :value="$eur($total)" />
        <x-kpi-tile :label="__('Artikel mit Umsatz')" :value="$articleCount" />
        <x-kpi-tile :label="__('Anteil ohne Artikelbezug')" :value="$withoutShare !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($withoutShare, 1) . ' %' : '–'"
                    :tone="($withoutShare ?? 0) > 50 ? 'warning' : 'neutral'"
                    :hint="__('Positionen ohne Artikel — Picker in Rechnung/Angebot pflegen.')" />
    </div>

    <x-charts.bar-h :title="__('Nettoumsatz je Artikel (Top :n)', ['n' => $topN])" unit="€" :series="$series" :x-label="__('Artikel')" y-label="€"
                    :note="__('Datenbasis: Positionen ausgestellter und bezahlter lokaler Rechnungen (Rechnung/Abschlag/Schluss); Klick öffnet den Artikel.')" />

    <x-table scroll="flex" :zebra="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Artikelnummer') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Artikel') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Einheit') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Nettoumsatz') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Anteil') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Rechnungen') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr @class(['text-base-content/70 italic' => $row['articleId'] === null])>
                <td class="font-mono text-xs">{{ $row['number'] ?? '—' }}</td>
                <td class="font-medium">
                    @if ($row['articleId'] !== null)
                        <a href="{{ route('articles.show', \App\Support\Sqid::encode(\App\Models\Article::class, $row['articleId'])) }}" class="link link-hover">{{ $row['name'] }}</a>
                    @else
                        {{ $row['name'] }}
                    @endif
                </td>
                <td class="text-right tabular-nums" data-sort-value="{{ $row['quantity'] }}">{{ $qty($row['quantity']) }}</td>
                <td>{{ $row['unit'] ?? '—' }}</td>
                <td class="text-right tabular-nums" data-sort-value="{{ $row['net'] }}">{{ $eur($row['net']) }}</td>
                <td class="text-right tabular-nums" data-sort-value="{{ $row['share'] ?? 0 }}">{{ $row['share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['share'], 1) . ' %' : '–' }}</td>
                <td class="text-right tabular-nums">{{ $row['invoices'] }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="7" icon="inventory" :title="__('Keine Rechnungspositionen im gewählten Zeitraum.')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
