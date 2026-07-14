{{--
  Created on   : Sun Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Detail-Scorecard eines Lieferanten (Bauturbo Welle D): Gesamt-Score,
     Kennzahlkacheln mit Ampel + „keine Daten"-Ausweis, Verläufe (x-charts.*)
     und signierte, kurzlebige Beleg-Drilldowns auf die Quellbelege. --}}

@extends('layouts.app')
@section('title', __('scorecard.title') . ' — ' . $supplier->name)
@section('nav-title', __('scorecard.title'))

@php
    /** @var array<string, mixed> $card */
    $ontime = $card['ontime'];
    $complaints = $card['complaints'];
    $price = $card['price'];
    $quality = $card['quality'];

    $toneFor = static fn(?int $g): string => match (true) {
        $g === null => 'ghost',
        $g >= 80 => 'success',
        $g >= 50 => 'warning',
        default => 'error',
    };
    $drill = fn(string $kind): string => \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'supplier-scorecards.drilldown',
        now()->addMinutes(30),
        ['supplier' => $supplier, 'kind' => $kind, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
    );
@endphp

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $supplier->name }}</x-slot:title>
        <x-slot:subtitle>{{ __('scorecard.detail_subtitle', ['version' => $card['metric_version'], 'label' => $label]) }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="list" tone="ghost" size="sm" :href="route('supplier-scorecards.index')" show-label>{{ __('scorecard.back_to_ranking') }}</x-icon-btn>
            <x-icon-btn icon="local_shipping" tone="ghost" size="sm" :href="route('suppliers.show', $supplier)" show-label>{{ __('scorecard.supplier_master') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    {{-- Gesamt-Score --}}
    <x-card :title="__('scorecard.overall_title')">
        <div class="flex items-baseline gap-3">
            @if ($card['overall'] === null)
                <span class="text-2xl font-semibold text-base-content/40">{{ __('scorecard.no_data') }}</span>
            @else
                <span class="text-4xl font-semibold tabular-nums">{{ $card['overall'] }}</span>
                <span class="text-base-content/60">/ 100</span>
            @endif
        </div>
        <p class="mt-1 text-xs text-base-content/60">{{ __('scorecard.overall_hint') }}</p>
    </x-card>

    {{-- Kennzahlkacheln --}}
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        {{-- Termintreue --}}
        <x-card :title="__('scorecard.metric_ontime')">
            @if (! $ontime['available'])
                <p class="text-base-content/40">{{ __('scorecard.no_data') }}</p>
                <p class="text-xs text-base-content/50">{{ __('scorecard.ontime_no_source') }}</p>
            @else
                <p class="text-2xl font-semibold tabular-nums">{{ round($ontime['rate'] * 100) }} %</p>
                <p class="text-xs text-base-content/60">{{ __('scorecard.ontime_detail', ['on' => $ontime['on_time'], 'total' => $ontime['evaluated']]) }}</p>
                <x-status-badge :tone="$toneFor($ontime['goodness'])" size="sm" class="mt-1">{{ __('scorecard.goodness', ['g' => $ontime['goodness']]) }}</x-status-badge>
            @endif
            <a class="mt-2 inline-block text-xs link link-hover" href="{{ $drill('deliveries') }}">{{ __('scorecard.drill_deliveries') }} →</a>
        </x-card>

        {{-- Reklamationsquote --}}
        <x-card :title="__('scorecard.metric_complaints')">
            @if (! $complaints['available'])
                <p class="text-base-content/40">{{ __('scorecard.no_data') }}</p>
                <p class="text-xs text-base-content/50">{{ __('scorecard.complaints_no_source') }}</p>
            @else
                <p class="text-2xl font-semibold tabular-nums">{{ round($complaints['rate'] * 100) }} %</p>
                <p class="text-xs text-base-content/60">{{ __('scorecard.complaints_detail', ['count' => $complaints['count'], 'base' => $complaints['base']]) }}</p>
                <x-status-badge :tone="$toneFor($complaints['goodness'])" size="sm" class="mt-1">{{ __('scorecard.goodness', ['g' => $complaints['goodness']]) }}</x-status-badge>
            @endif
            <a class="mt-2 inline-block text-xs link link-hover" href="{{ $drill('claims') }}">{{ __('scorecard.drill_claims') }} →</a>
        </x-card>

        {{-- Preisentwicklung --}}
        <x-card :title="__('scorecard.metric_price')">
            @if (! $price['available'])
                <p class="text-base-content/40">{{ __('scorecard.no_data') }}</p>
                <p class="text-xs text-base-content/50">{{ __('scorecard.price_no_source') }}</p>
            @else
                @php $dir = $price['direction']; @endphp
                <p class="text-2xl font-semibold tabular-nums inline-flex items-center gap-1 {{ $dir === 'up' ? 'text-error' : ($dir === 'down' ? 'text-success' : '') }}">
                    <x-icon :name="$dir === 'up' ? 'trending_up' : ($dir === 'down' ? 'trending_down' : 'trending_flat')" />
                    {{ ($price['trend_pct'] > 0 ? '+' : '') . $price['trend_pct'] }} %
                </p>
                <p class="text-xs text-base-content/60">{{ __('scorecard.price_dir_' . $dir) }}</p>
                <x-status-badge :tone="$toneFor($price['goodness'])" size="sm" class="mt-1">{{ __('scorecard.goodness', ['g' => $price['goodness']]) }}</x-status-badge>
            @endif
            <a class="mt-2 inline-block text-xs link link-hover" href="{{ $drill('prices') }}">{{ __('scorecard.drill_prices') }} →</a>
        </x-card>

        {{-- ISMS-Qualität --}}
        <x-card :title="__('scorecard.metric_quality')">
            @if (! $quality['available'])
                <p class="text-base-content/40">{{ __('scorecard.no_data') }}</p>
                <p class="text-xs text-base-content/50">{{ __('scorecard.quality_no_source') }}</p>
            @else
                <x-status-badge :tone="$quality['rating']->tone()" size="lg">{{ $quality['rating']->label() }}</x-status-badge>
                <p class="mt-1 text-xs text-base-content/60">{{ __('scorecard.quality_detail') }}</p>
                @if ($quality['assessment'])
                    <a class="mt-2 inline-block text-xs link link-hover" href="{{ route('isms.suppliers.edit', $quality['assessment']) }}">{{ $quality['assessment']->displayNo() }} →</a>
                @endif
            @endif
        </x-card>
    </div>

    {{-- Verläufe --}}
    <div class="grid gap-3 xl:grid-cols-2">
        <x-charts.line :title="__('scorecard.chart_ontime')"
                       :unit="__('scorecard.unit_percent')"
                       :computed-at="$card['computed_at']"
                       :x-label="__('scorecard.axis_month')"
                       :series="$ontime['series']" />

        <x-charts.line :title="__('scorecard.chart_price_index')"
                       :unit="__('scorecard.unit_index')"
                       :computed-at="$card['computed_at']"
                       :x-label="__('scorecard.axis_month')"
                       :series="$price['series']" />

        <x-charts.bar :title="__('scorecard.chart_complaints')"
                      :unit="__('scorecard.unit_count')"
                      :computed-at="$card['computed_at']"
                      :x-label="__('scorecard.axis_month')"
                      :series="$complaints['series']" />

        {{-- Preisentwicklung je Artikel --}}
        <x-card :title="__('scorecard.price_articles')" padding="p-0">
            @if ($price['articles'] === [])
                <div class="p-4"><x-empty-state icon="sell" :title="__('scorecard.no_data')" compact /></div>
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('scorecard.col_article') }}</th>
                            <th class="text-right">{{ __('scorecard.col_first_price') }}</th>
                            <th class="text-right">{{ __('scorecard.col_last_price') }}</th>
                            <th class="text-right">{{ __('scorecard.col_change') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($price['articles'] as $article)
                        <tr class="hover">
                            <td>{{ $article['article'] }}</td>
                            <td class="text-right tabular-nums">{{ number_format($article['first'], 2, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($article['last'], 2, ',', '.') }}</td>
                            <td class="text-right tabular-nums {{ $article['pct'] > 0 ? 'text-error' : ($article['pct'] < 0 ? 'text-success' : '') }}">
                                {{ ($article['pct'] > 0 ? '+' : '') . $article['pct'] }} %
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</x-page-shell>
@endsection
