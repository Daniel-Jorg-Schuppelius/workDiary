{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tour-plan-actual.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Touren Plan-Ist'))
@section('nav-title', __('Touren Plan-Ist'))
@section('content')
@php
    $num = fn (float|int $v, int $d = 0) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, withThousandsSeparator: true);
    $selectedUser = $userId !== null ? $users->firstWhere('id', $userId) : null;
    $linkParams = array_filter(['user' => $selectedUser?->sqid, 'from' => $from, 'to' => $to]);
    $signed = fn (?int $v): string => $v === null ? '—' : (($v > 0 ? '+' : '') . $v . ' min');
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Geplante und tatsächliche Ankunft und Verweildauer je Stopp; Abweichungen je Fahrer und Auftragsart speisen die Erfahrungswerte der Servicedauer.')">
            <x-slot:actions>
                <x-report-export :url="fn (string $format) => route('reports.tour-plan-actual', array_merge($linkParams, ['export' => $format]))" :formats="['csv', 'xlsx']" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>
    <x-filter-bar :action="route('reports.tour-plan-actual')" :reset="route('reports.tour-plan-actual')">
        @if ($users->count() > 1)
            <x-filter-field :label="__('Fahrer')" for="tpa-user">
                <select id="tpa-user" name="user" class="select select-sm select-bordered" data-autosubmit>
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->sqid }}" @selected($userId !== null && (int) $u->id === $userId)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
    </x-filter-bar>
    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Stopps mit Ist')" :value="$totals['stops']" :hint="$totals['done'] . ' ' . __('erledigt') . ' · ' . $totals['not_met'] . ' ' . __('nicht angetroffen')" />
        <x-kpi-tile :label="__('Ø Abweichung Ankunft')" :value="$totals['avg_arrival_delta'] !== null ? $signed((int) round($totals['avg_arrival_delta'])) : '—'" />
        <x-kpi-tile :label="__('Anteil verspätet (> 5 min)')" :value="$totals['late_share'] !== null ? $num($totals['late_share'], 1) . ' %' : '—'" />
        <x-kpi-tile :label="__('Ø Abweichung Dauer')" :value="$totals['avg_service_delta'] !== null ? $signed((int) round($totals['avg_service_delta'])) : '—'" />
    </div>
    @if ($byType !== [])
        <x-card padding="p-0">
            <div class="border-b border-base-300 px-4 py-2 text-sm font-medium">{{ __('Verweildauer je Auftragsart') }}</div>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Auftragsart') }}</th>
                        <th class="text-right">{{ __('Stopps') }}</th>
                        <th class="text-right">{{ __('Ø geplant') }}</th>
                        <th class="text-right">{{ __('Ø Ist') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($byType as $g)
                    <tr>
                        <td>{{ $g['label'] }}</td>
                        <td class="text-right">{{ $g['stops'] }}</td>
                        <td class="text-right">{{ $g['avg_planned'] !== null ? $num($g['avg_planned'], 1) . ' min' : '—' }}</td>
                        <td class="text-right">{{ $num($g['avg_actual'], 1) }} min</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
    <x-card padding="p-0">
        @if ($rows === [])
            <x-empty-state icon="route" :title="__('Keine Stopps mit Ist-Zeiten im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Tour') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Fahrer') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Auftrag') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Ankunft geplant') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Ankunft Ist') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Abweichung') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Dauer geplant') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Dauer Ist') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Ergebnis') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $r)
                    @php($stop = $r['stop'])
                    <tr>
                        <td>{{ $stop->tour?->tour_date?->fdate() }}</td>
                        <td><a href="{{ route('tours.show', $stop->tour) }}" class="link">{{ $stop->tour?->name ?? ('#' . $stop->tour_id) }}</a></td>
                        <td>{{ $stop->tour?->user?->name }}</td>
                        <td>@if ($stop->diaryEntry)<x-order-link :entry="$stop->diaryEntry" link-class="link">{{ $stop->diaryEntry->title }}</x-order-link>@else — @endif</td>
                        <td>{{ \App\Support\Tz::toLocal($stop->planned_arrival_at)?->format('H:i') ?? '—' }}</td>
                        <td>{{ \App\Support\Tz::toLocal($stop->arrived_at)?->format('H:i') ?? '—' }}</td>
                        <td class="text-right {{ ($r['arrival_delta'] ?? 0) > 5 ? 'text-warning' : '' }}">{{ $signed($r['arrival_delta']) }}</td>
                        <td class="text-right">{{ $r['planned_service'] !== null ? $r['planned_service'] . ' min' : '—' }}</td>
                        <td class="text-right">{{ $r['actual_service'] !== null ? $r['actual_service'] . ' min' : '—' }}</td>
                        <td>
                            @if ($stop->outcome === \App\Models\Diary\TourStop::OUTCOME_DONE)
                                <x-status-badge tone="success" size="xs">{{ __('erledigt') }}</x-status-badge>
                            @elseif ($stop->outcome === \App\Models\Diary\TourStop::OUTCOME_NOT_MET)
                                <x-status-badge tone="warning" size="xs">{{ __('nicht angetroffen') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost" size="xs">{{ __('offen') }}</x-status-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
