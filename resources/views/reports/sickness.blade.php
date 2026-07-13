@extends('layouts.app')
@section('title', __('Krankheits-Report'))
@section('nav-title', __('Krankheits-Report'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Krankheitsfälle, AU-Bescheinigungen und Lohnfortzahlung je Mitarbeiter.')" />
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.sickness')" :reset="route('reports.sickness')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <x-kpi-tile :label="__('Mitarbeiter')" :value="$totals['users']" />
        <x-kpi-tile :label="__('Werktage krank')" :value="$totals['sick_workdays']" :hint="$totals['sick_calendar_days'] . ' ' . __('Kalendertage')" />
        <x-kpi-tile :label="__('Krankheitsfälle')" :value="$totals['episodes']" :hint="$totals['follow_ups'] . ' ' . __('Folge')" />
        <x-kpi-tile :label="__('Mit AU')" :value="$totals['with_au']" :hint="'/ ' . $totals['episodes'] . ' ' . __('Fälle')" />
        <x-kpi-tile :label="__('Anspruch ausgeschöpft')" :value="$totals['exhausted']" :tone="$totals['exhausted'] > 0 ? 'error' : 'neutral'" />
    </div>

    <x-card>
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">sick</span>' :title="__('Keine Krankheitsdaten im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Werktage') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Kal.-Tage') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Fälle') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Folge') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Mit AU') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Lohnfortzahlung') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($rows as $r)
                    @php
                        $pct = $r['entitlement_days'] > 0 ? (int) min(100, round($r['used_days'] / $r['entitlement_days'] * 100)) : 0;
                        $tone = $r['exhausted'] ? 'error' : ($pct >= 75 ? 'warning' : 'success');
                    @endphp
                    <tr>
                        <td class="font-semibold">
                            {{ $r['user']->name }}
                            @if ($r['chain_start'])
                                <div class="text-xs text-base-content/60">
                                    {{ __('Kette seit') }} {{ \Carbon\Carbon::parse($r['chain_start'])->fdate() }}
                                </div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $r['sick_workdays'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['sick_calendar_days'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['episodes'] }}</td>
                        <td class="text-right tabular-nums">{{ $r['follow_ups'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (int) $r['with_au'] }}">{{ $r['with_au'] }} / {{ $r['episodes'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $pct }}">
                            <div class="flex items-center gap-2 justify-end">
                                <progress class="progress progress-{{ $tone }} w-20" value="{{ $r['used_days'] }}" max="{{ $r['entitlement_days'] }}"></progress>
                                <span class="text-xs">{{ $r['used_days'] }} / {{ $r['entitlement_days'] }}</span>
                            </div>
                        </td>
                        <td>
                            @if ($r['exhausted'])
                                <x-status-badge tone="error">{{ __('Ausgeschöpft') }}</x-status-badge>
                                @if ($r['exhaustion_date'])
                                    <div class="text-xs text-base-content/60">
                                        {{ \Carbon\Carbon::parse($r['exhaustion_date'])->fdate() }}
                                    </div>
                                @endif
                            @elseif ($r['used_days'] > 0)
                                <x-status-badge :tone="$tone">{{ __(':n Tage frei', ['n' => $r['remaining_days']]) }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('OK') }}</x-status-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>

</x-page-shell>
@endsection
