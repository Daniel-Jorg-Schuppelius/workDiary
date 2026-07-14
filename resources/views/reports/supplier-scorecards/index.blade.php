{{--
  Created on   : Sun Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Lieferantenperformance-Scorecards (Bauturbo Welle D): Ranking nach
     Gesamt-Score mit Ampel je Kennzahl (Termintreue/Reklamationsquote/
     Preisentwicklung/ISMS-Qualität). „Keine Daten" wird ausgewiesen, nie als 0
     verfälscht. Berechnung über SupplierScorecardService (Definition v.). --}}

@extends('layouts.app')
@section('title', __('scorecard.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('scorecard.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $rows */
    $toneFor = static fn(?int $g): string => match (true) {
        $g === null => 'ghost',
        $g >= 80 => 'success',
        $g >= 50 => 'warning',
        default => 'error',
    };
    $rateTone = static function (?float $rate, bool $inverse) use ($toneFor): string {
        if ($rate === null) {
            return 'ghost';
        }
        $g = $inverse ? (int) round(max(0.0, min(100.0, 100.0 - $rate * 100.0))) : (int) round($rate * 100.0);
        return $toneFor($g);
    };
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('scorecard.overview_subtitle', ['version' => $metricVersion])">
    <x-slot:actions>
        <span class="text-xs text-base-content/60">{{ $label }}</span>
    </x-slot:actions>

    <x-filter-bar :action="route('supplier-scorecards.index')" :reset="route('supplier-scorecards.index')">
        <x-date-range from-name="from" to-name="to" :from="$from->toDateString()" :to="$to->toDateString()" size="sm" />
        <x-icon-btn icon="filter_alt" tone="ghost" size="sm" type="submit" show-label>{{ __('scorecard.apply') }}</x-icon-btn>
    </x-filter-bar>

    <div class="alert alert-info text-xs">
        <x-icon name="info" />
        <span>{{ __('scorecard.weights_hint', [
            'ontime' => round($weights['ontime'] * 100),
            'complaints' => round($weights['complaints'] * 100),
            'quality' => round($weights['quality'] * 100),
            'price' => round($weights['price'] * 100),
        ]) }}</span>
    </div>

    @if ($rows->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>'
                       :title="__('scorecard.empty_ranking')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th class="w-10 text-right">#</th>
                        <th>{{ __('scorecard.col_supplier') }}</th>
                        <th class="text-right">{{ __('scorecard.col_overall') }}</th>
                        <th class="text-center">{{ __('scorecard.metric_ontime') }}</th>
                        <th class="text-center">{{ __('scorecard.metric_complaints') }}</th>
                        <th class="text-center">{{ __('scorecard.metric_price') }}</th>
                        <th class="text-center">{{ __('scorecard.metric_quality') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $i => $row)
                    <tr class="hover">
                        <td class="text-right tabular-nums text-base-content/60">{{ $rows->firstItem() + $i }}</td>
                        <td>
                            <a class="link link-hover font-medium" href="{{ route('supplier-scorecards.show', $row['supplier']) }}">{{ $row['supplier_name'] }}</a>
                        </td>
                        <td class="text-right">
                            @if ($row['overall'] === null)
                                <span class="text-base-content/40">{{ __('scorecard.no_data') }}</span>
                            @else
                                <span class="font-semibold tabular-nums">{{ $row['overall'] }}</span>
                            @endif
                        </td>
                        {{-- Termintreue --}}
                        <td class="text-center">
                            @if (! $row['ontime_available'])
                                <span class="text-base-content/40 text-xs">{{ __('scorecard.no_data') }}</span>
                            @else
                                <x-status-badge :tone="$rateTone($row['ontime_rate'], false)" size="sm">{{ round($row['ontime_rate'] * 100) }} %</x-status-badge>
                            @endif
                        </td>
                        {{-- Reklamationsquote --}}
                        <td class="text-center">
                            @if (! $row['complaint_available'])
                                <span class="text-base-content/40 text-xs">{{ __('scorecard.no_data') }}</span>
                            @else
                                <x-status-badge :tone="$rateTone($row['complaint_rate'], true)" size="sm">{{ round($row['complaint_rate'] * 100) }} %</x-status-badge>
                            @endif
                        </td>
                        {{-- Preisentwicklung --}}
                        <td class="text-center">
                            @if (! $row['price_available'])
                                <span class="text-base-content/40 text-xs">{{ __('scorecard.no_data') }}</span>
                            @else
                                @php $dir = $row['price_direction']; @endphp
                                <span class="inline-flex items-center gap-1 tabular-nums {{ $dir === 'up' ? 'text-error' : ($dir === 'down' ? 'text-success' : 'text-base-content/70') }}">
                                    <x-icon :name="$dir === 'up' ? 'trending_up' : ($dir === 'down' ? 'trending_down' : 'trending_flat')" class="text-sm" />
                                    {{ ($row['price_trend_pct'] > 0 ? '+' : '') . $row['price_trend_pct'] }} %
                                </span>
                            @endif
                        </td>
                        {{-- ISMS-Qualität --}}
                        <td class="text-center">
                            @if (! $row['quality_available'])
                                <span class="text-base-content/40 text-xs">{{ __('scorecard.no_data') }}</span>
                            @else
                                <x-status-badge :tone="$row['quality_rating']->tone()" size="sm">{{ $row['quality_rating']->label() }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <x-icon-btn icon="chevron_right" :href="route('supplier-scorecards.show', $row['supplier'])" :label="__('scorecard.open_detail')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-pagination :paginator="$rows" standing />
    @endif
</x-index-page>
@endsection
