@extends('layouts.app')
@section('title', __('Krankheits-Report'))
@section('nav-title', __('Krankheits-Report'))

@section('content')
<x-page-shell>

    <x-filter-bar :action="route('reports.sickness')" :reset="route('reports.sickness')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
    </x-filter-bar>

    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('Mitarbeiter') }}</div>
            <div class="stat-value text-2xl">{{ $totals['users'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Werktage krank') }}</div>
            <div class="stat-value text-2xl">{{ $totals['sick_workdays'] }}</div>
            <div class="stat-desc">{{ $totals['sick_calendar_days'] }} {{ __('Kalendertage') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Krankheitsfälle') }}</div>
            <div class="stat-value text-2xl">{{ $totals['episodes'] }}</div>
            <div class="stat-desc">{{ $totals['follow_ups'] }} {{ __('Folge') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Mit AU') }}</div>
            <div class="stat-value text-2xl">{{ $totals['with_au'] }}</div>
            <div class="stat-desc">/ {{ $totals['episodes'] }} {{ __('Fälle') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Anspruch ausgeschöpft') }}</div>
            <div class="stat-value text-2xl {{ $totals['exhausted'] > 0 ? 'text-error' : '' }}">{{ $totals['exhausted'] }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if (empty($rows))
            <x-empty-state :title="__('Keine Krankheitsdaten im gewählten Zeitraum.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th class="text-right">{{ __('Werktage') }}</th>
                            <th class="text-right">{{ __('Kal.-Tage') }}</th>
                            <th class="text-right">{{ __('Fälle') }}</th>
                            <th class="text-right">{{ __('Folge') }}</th>
                            <th class="text-right">{{ __('Mit AU') }}</th>
                            <th class="text-right">{{ __('Lohnfortzahlung') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                            {{ __('Kette seit') }} {{ \Carbon\Carbon::parse($r['chain_start'])->format('d.m.Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $r['sick_workdays'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['sick_calendar_days'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['episodes'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['follow_ups'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['with_au'] }} / {{ $r['episodes'] }}</td>
                                <td class="text-right tabular-nums">
                                    <div class="flex items-center gap-2 justify-end">
                                        <progress class="progress progress-{{ $tone }} w-20" value="{{ $r['used_days'] }}" max="{{ $r['entitlement_days'] }}"></progress>
                                        <span class="text-xs">{{ $r['used_days'] }} / {{ $r['entitlement_days'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($r['exhausted'])
                                        <span class="badge badge-sm badge-error">{{ __('Ausgeschöpft') }}</span>
                                        @if ($r['exhaustion_date'])
                                            <div class="text-xs text-base-content/60">
                                                {{ \Carbon\Carbon::parse($r['exhaustion_date'])->format('d.m.Y') }}
                                            </div>
                                        @endif
                                    @elseif ($r['used_days'] > 0)
                                        <span class="badge badge-sm badge-{{ $tone }}">{{ __(':n Tage frei', ['n' => $r['remaining_days']]) }}</span>
                                    @else
                                        <span class="badge badge-sm badge-ghost">{{ __('OK') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-page-shell>
@endsection
