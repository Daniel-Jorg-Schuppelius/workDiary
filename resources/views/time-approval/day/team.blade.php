{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : team.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Team heute (MVP-884): Anwesenheit jetzt und offene Tagesabschlüsse.
  Abwesende ohne Grund, wie die Belegungstafel.
--}}

@extends('layouts.app')

@section('title', __('day-close.team.title'))
@section('nav-title', __('day-close.team.title'))

@section('content')
    @php
        $present = [...$snapshot['present'], ...$snapshot['present_unmapped']];
    @endphp
    <x-index-page :subtitle="__('day-close.team.subtitle')">
        <div class="grid gap-4 lg:grid-cols-3">
            <x-card :title="__('day-close.team.present')" icon="how_to_reg" :count="count($present)">
                @if ($present === [])
                    <x-empty-state compact icon="person_off" :title="__('day-close.team.nobody_present')" />
                @else
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($present as $row)
                            <li class="flex items-center justify-between gap-2 py-1.5">
                                <span class="truncate">{{ $row['user']->name }}</span>
                                <span class="shrink-0 text-xs text-muted tabular-nums">
                                    @if ($row['on_break'])
                                        {{ __('day-close.team.on_break') }}
                                    @elseif ($row['since'])
                                        {{ __('day-close.team.since', ['time' => $row['since']->tz(\App\Support\Tz::current())->format('H:i')]) }}
                                    @endif
                                    @if ($row['site_name']) · {{ $row['site_name'] }}@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-card :title="__('day-close.team.away')" icon="event_busy" :count="count($snapshot['absent']) + count($snapshot['off_site'])">
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($snapshot['off_site'] as $row)
                        <li class="flex items-center justify-between gap-2 py-1.5">
                            <span class="truncate">{{ $row['user']->name }}</span>
                            <span class="text-xs text-muted">{{ __('day-close.team.off_site') }}</span>
                        </li>
                    @endforeach
                    @foreach ($snapshot['absent'] as $row)
                        <li class="flex items-center justify-between gap-2 py-1.5">
                            <span class="truncate">{{ $row['user']->name }}</span>
                            <span class="text-xs text-muted">{{ __('day-close.team.absent') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
            <x-card :title="__('day-close.team.unaccounted')" icon="help" :count="count($snapshot['unaccounted'])">
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($snapshot['unaccounted'] as $row)
                        <li class="py-1.5">{{ $row['user']->name }}</li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <x-card :title="__('day-close.team.open_days')" icon="pending_actions" :count="count($openDays)">
            <x-slot:actions>
                <form method="GET" action="{{ route('day-close.team') }}" class="flex items-end gap-2">
                    <x-date-range from-name="from" to-name="to" :from="$from->toDateString()" :to="$to->toDateString()" />
                    <x-icon-btn icon="filter_alt" size="sm" type="submit" :label="__('day-close.team.apply')" />
                </form>
            </x-slot:actions>
            @if ($openDays === [])
                <x-empty-state compact icon="task_alt" :title="__('day-close.team.no_open_days')" />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('day-close.team.day') }}</th>
                            <th>{{ __('day-close.team.person') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($openDays as $row)
                        <tr>
                            <td class="tabular-nums">{{ $row['day']->isoFormat('dd, L') }}</td>
                            <td>{{ $row['user']->name }}</td>
                            <td class="text-right">
                                <x-icon-btn icon="open_in_new" size="xs" :href="route('day-close.show', ['date' => $row['day']->toDateString(), 'user' => $row['user']->sqid])" :label="__('day-close.team.open')" />
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </x-index-page>
@endsection
