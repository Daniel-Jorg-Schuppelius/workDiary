{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tender-cockpit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Vergabe-Cockpit'))
@section('nav-title', __('Vergabe-Cockpit'))

@php
    /** @var array<string, array{count: int, value: float}> $pipeline */
    /** @var list<array{name: string, open: int, soon: int, overdue: int, value: float}> $workload */
    $money = static fn (float $value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true) . ' €';
    $windowLabels = [
        'overdue' => __('Überfällig'),
        'week' => __('Diese Woche'),
        'fortnight' => __('In 2 Wochen'),
        'month' => __('In 1 Monat'),
        'later' => __('Später'),
        'none' => __('Ohne Frist'),
    ];
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">
                {{ __('Zeitraum: :from – :to · Fristensichten zeigen den offenen Bestand unabhängig vom Zeitraum.', ['from' => $from, 'to' => $to]) }}
            </div>
            <x-slot:actions>
                <x-icon-btn icon="radar" size="sm" :href="route('tender-radar.index')" show-label>{{ __('Radar') }}</x-icon-btn>
                <x-icon-btn icon="download" size="sm" :href="route('tenders.cockpit', ['from' => $from, 'to' => $to, 'export' => 'csv'])" show-label>{{ __('CSV') }}</x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" :href="route('tenders.cockpit', ['from' => $from, 'to' => $to, 'export' => 'xlsx'])" show-label>Excel</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Die Fristenkacheln zuerst: Eine versäumte Abgabefrist ist kein
         verlorenes Angebot, sondern gar keines. --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-tile :label="__('Überfällige Abgaben')" :value="$deadlines['overdue']['count']"
                    :tone="$deadlines['overdue']['count'] > 0 ? 'error' : 'neutral'"
                    :hint="__('Offene Vorgänge mit abgelaufener Frist')"
                    :href="route('tenders.index', ['open_only' => 1])" />
        <x-kpi-tile :label="__('Abgaben in 14 Tagen')" :value="$deadlines['week']['count'] + $deadlines['fortnight']['count']"
                    tone="warning" :hint="__('Nächste zwei Wochen')" />
        <x-kpi-tile :label="__('Trefferquote')" :value="$decision['win_rate'] !== null ? $decision['win_rate'] . ' %' : '—'"
                    tone="primary" format="raw"
                    :hint="__(':won gewonnen / :lost verloren', ['won' => $decision['won'], 'lost' => $decision['lost']])" />
        <x-kpi-tile :label="__('Gewonnener Wert')" :value="$money($decision['won_value'])" format="raw"
                    tone="success" :hint="__('Wertpotenzial gewonnener Vorgänge im Zeitraum')" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.bar :title="__('Offene Abgaben nach Fristfenster')" :unit="__('Vorgänge')"
                      :series="$deadlineSeries" :x-label="__('Fristfenster')" :y-label="__('Vorgänge')"
                      :note="__('Offener Bestand, unabhängig vom gewählten Zeitraum.')" />
        <x-charts.bar-h :title="__('Fristenlast je Verantwortlichem')" :unit="__('Vorgänge')"
                        :series="$workloadSeries" :x-label="__('Verantwortlich')" :y-label="__('Offen')"
                        :y2-label="__('Davon fällig ≤ 14 Tage oder überfällig')"
                        :note="__('Nicht die Menge entscheidet, sondern wie viel gleichzeitig fällig wird.')" />
    </div>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.line :title="__('Wertpotenzial :per', ['per' => $periodPhrase])" :unit="__('EUR')"
                       :series="$valueSeries" :x-label="$periodAxis" :y-label="__('EUR')" />
        <x-charts.bar :title="__('Pipeline nach Status')" :unit="__('Vorgänge')"
                      :series="$pipelineSeries" :x-label="__('Status')" :y-label="__('Vorgänge')" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Fristfenster') }}</h3>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Fenster') }}</th>
                        <th class="text-right">{{ __('Vorgänge') }}</th>
                        <th class="text-right">{{ __('Wertpotenzial') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($deadlines as $key => $row)
                    <tr>
                        <td @class(['text-error font-medium' => $key === 'overdue' && $row['count'] > 0])>{{ $windowLabels[$key] ?? $key }}</td>
                        <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['value']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Fristenlast') }}</h3>
            @if (empty($workload))
                <x-empty-state icon="schedule" :title="__('Keine offenen Vorgänge.')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Verantwortlich') }}</th>
                            <th class="text-right">{{ __('Offen') }}</th>
                            <th class="text-right">{{ __('≤ 14 Tage') }}</th>
                            <th class="text-right">{{ __('Überfällig') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($workload as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['open'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['soon'] }}</td>
                            <td class="text-right tabular-nums {{ $row['overdue'] > 0 ? 'text-error font-medium' : '' }}">{{ $row['overdue'] }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Pipeline') }}</h3>
            @if (empty($pipeline))
                <x-empty-state icon="gavel" :title="__('Keine Vorgänge im Zeitraum.')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr><th>{{ __('Status') }}</th><th class="text-right">{{ __('Anzahl') }}</th><th class="text-right">{{ __('Wertpotenzial') }}</th></tr>
                    </x-slot:head>
                    @foreach ($pipeline as $status => $row)
                        <tr>
                            <td>{{ __("values.$status") }}</td>
                            <td class="text-right tabular-nums">{{ $row['count'] }}</td>
                            <td class="text-right tabular-nums">{{ $money($row['value']) }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Verlustgründe') }}</h3>
            @if (empty($decision['loss_reasons']))
                <x-empty-state icon="thumb_down" :title="__('Keine Verluste im Zeitraum.')" />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($decision['loss_reasons'] as $reason => $count)
                        <li class="flex justify-between gap-4"><span>{{ $reason }}</span><span class="tabular-nums">{{ $count }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Verfahrensarten') }}</h3>
            @if (empty($procedures))
                <x-empty-state icon="balance" :title="__('Keine Verfahrensart erfasst.')" />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($procedures as $label => $count)
                        <li class="flex justify-between gap-4"><span>{{ $label }}</span><span class="tabular-nums">{{ $count }}</span></li>
                    @endforeach
                </ul>
            @endif

            <h3 class="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-base-content/70">{{ __('Bekanntmachungs-Radar') }}</h3>
            <ul class="space-y-1 text-sm">
                <li class="flex justify-between gap-4"><span>{{ __('Offene Treffer') }}</span><span class="tabular-nums">{{ $radar['new'] }}</span></li>
                <li class="flex justify-between gap-4"><span>{{ __('Übernommen') }}</span><span class="tabular-nums">{{ $radar['converted'] }}</span></li>
                <li class="flex justify-between gap-4"><span>{{ __('Ausgeblendet') }}</span><span class="tabular-nums">{{ $radar['muted'] }}</span></li>
            </ul>
        </x-card>
    </div>

    @if (!empty($lossSeries))
        <x-charts.bar-h :title="__('Verlustgründe')" :unit="__('Vorgänge')" :series="$lossSeries"
                        :x-label="__('Grund')" :y-label="__('Anzahl')" />
    @endif
</x-page-shell>
@endsection
