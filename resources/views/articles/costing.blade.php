{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : costing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $article->name . ' — ' . __('article.costing.title'))
@section('nav-title', __('article.title'))

@php
    /** @var \App\Models\Article $article */
    /** @var array<string, mixed> $result */
    $eur = fn (string $v, int $d = 2): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, withThousandsSeparator: true) . ' €';
    $qty = fn (string $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2);
    $pct = fn (?string $v): string => $v === null ? '–' : (((float) $v > 0 ? '+' : '') . \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 1) . ' %');
    $devTone = fn (?string $v): string => $v === null ? '' : ((float) $v > 0 ? 'text-error' : ((float) $v < 0 ? 'text-success' : ''));
    $rangeParams = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$article->name" :subtitle="__('article.costing.subtitle')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('articles.costing', [$article, ...$rangeParams, 'export' => 'csv'])"
                            show-label>CSV</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('articles._tabs', ['article' => $article])

    <x-filter-bar :action="route('articles.costing', $article)" :reset="route('articles.costing', $article)">
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      from-id="costing-from" to-id="costing-to" :from="$from->toDateString()" :to="$to->toDateString()" />
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
        <x-kpi-tile :label="__('article.costing.kpi.orders')" :value="$result['order_count']" tone="primary" />
        <x-kpi-tile :label="__('article.costing.kpi.unit_cost_avg')" :value="$eur($result['unit_cost_avg'], 4)" tone="info"
                    :hint="$result['unit_cost_min'] !== null ? __('article.costing.kpi.unit_cost_range', ['min' => $eur($result['unit_cost_min'], 4), 'max' => $eur($result['unit_cost_max'] ?? '0', 4)]) : null" />
        <x-kpi-tile :label="__('article.costing.kpi.material')" :value="$eur($result['actual_material'])"
                    :hint="__('article.costing.kpi.planned', ['value' => $eur($result['planned_material'])])" />
        <x-kpi-tile :label="__('article.costing.kpi.deviation')" :value="$pct($result['deviation_pct'])"
                    :tone="(float) $result['deviation_abs'] > 0 ? 'warning' : 'success'"
                    :hint="$eur($result['deviation_abs'])" />
        <x-kpi-tile :label="__('article.costing.kpi.minutes')" :value="$result['actual_minutes']"
                    :hint="__('article.costing.kpi.planned', ['value' => $result['planned_minutes']])" />
        <x-kpi-tile :label="__('article.costing.kpi.scrap_rate')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $result['quality']['scrap_rate'] * 100, 1) . ' %'"
                    :tone="(float) $result['quality']['scrap_rate'] > 0.05 ? 'warning' : 'neutral'"
                    :hint="__('article.costing.kpi.scrap_hint', ['scrap' => $qty($result['quality']['scrap']), 'produced' => $qty($result['quality']['produced'])])" />
    </div>

    <x-card>
        <h2 class="font-semibold mb-3">{{ __('article.costing.per_order') }}</h2>
        <x-table bare table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('article.costing.col.order') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('article.costing.col.completed_at') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.planned_material') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.actual_material') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.labor') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.total') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.minutes') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.good') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.scrap') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.unit_cost') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('article.costing.col.deviation') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($result['orders'] as $row)
                <tr>
                    <td class="font-mono text-xs">{{ $row['number'] }}</td>
                    <td class="whitespace-nowrap tabular-nums">{{ $row['completed_at'] !== null ? \App\Support\CarbonFmt::fdate($row['completed_at']) : '—' }}</td>
                    <td class="text-right tabular-nums">{{ $eur($row['planned_material']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($row['actual_material']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($row['labor']) }}</td>
                    <td class="text-right tabular-nums font-medium">{{ $eur($row['total']) }}</td>
                    <td class="text-right tabular-nums">{{ $row['actual_minutes'] }} <span class="text-muted">/ {{ $row['planned_minutes'] }}</span></td>
                    <td class="text-right tabular-nums">{{ $qty($row['good']) }}</td>
                    <td class="text-right tabular-nums">{{ $qty($row['scrap']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($row['unit_cost'], 4) }}</td>
                    <td class="text-right tabular-nums {{ $devTone($row['deviation_pct']) }}">{{ $pct($row['deviation_pct']) }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="open_in_new" :href="route('manufacturing-orders.show', \App\Support\Sqid::encode(\App\Models\ManufacturingOrder::class, $row['order_id']))" :label="__('article.costing.open_order')" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="12" icon="calculate"
                               :title="__('article.costing.empty')" />
            @endforelse
            @if ($result['orders'] !== [])
                <tr class="font-semibold bg-base-200/60" data-sort-ignore>
                    <td>{{ __('article.costing.sum') }}</td>
                    <td></td>
                    <td class="text-right tabular-nums">{{ $eur($result['planned_material']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($result['actual_material']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($result['labor']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($result['total']) }}</td>
                    <td class="text-right tabular-nums">{{ $result['actual_minutes'] }} <span class="text-muted">/ {{ $result['planned_minutes'] }}</span></td>
                    <td class="text-right tabular-nums">{{ $qty($result['good']) }}</td>
                    <td class="text-right tabular-nums">{{ $qty($result['scrap']) }}</td>
                    <td class="text-right tabular-nums">{{ $eur($result['unit_cost_avg'], 4) }}</td>
                    <td class="text-right tabular-nums {{ $devTone($result['deviation_pct']) }}">{{ $pct($result['deviation_pct']) }}</td>
                    <td></td>
                </tr>
            @endif
        </x-table>
        <p class="mt-2 text-xs text-muted">{{ __('article.costing.note') }}</p>
    </x-card>
</x-page-shell>
@endsection
