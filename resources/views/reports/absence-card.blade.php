{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : absence-card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Fehlzeitenkarte (MVP-520): Jahreskalender einer Person (12 Monatszeilen ×
  31 Tageszellen) + Urlaubskonto-Block + Statistik je Fehlgrund.
--}}

@extends('layouts.app')
@section('title', __('Fehlzeitenkarte') . ' – ' . $user->name)
@section('nav-title', __('Fehlzeitenkarte'))

@section('content')
@php
    $toneBg = [
        'warning' => 'bg-warning/80',
        'error' => 'bg-error/70',
        'info' => 'bg-info/70',
        'neutral' => 'bg-base-content/30',
    ];
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$user->name . ' · ' . $year">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                            :href="route('reports.absence-calendar', ['year' => $year])" show-label>{{ __('Jahresübersicht') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (! $neutral)
        <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
            <x-kpi-tile :label="__('Anspruch inkl. Übertrag')" :value="$balance->totalDays()" format="decimal" />
            <x-kpi-tile :label="__('Genommen')" :value="$balance->takenDays" format="decimal" />
            <x-kpi-tile :label="__('Geplant (offen)')" :value="$balance->pendingDays" format="decimal" />
            <x-kpi-tile :label="__('Resturlaub')" :value="$balance->remainingAfterPendingDays()" format="decimal"
                        :tone="$balance->remainingAfterPendingDays() < 0 ? 'error' : 'success'" />
        </div>
    @endif

    <x-card>
        <div class="overflow-x-auto">
            <table class="table table-xs">
                <thead>
                    <tr>
                        <th class="w-24"></th>
                        @for ($d = 1; $d <= 31; $d++)
                            <th class="text-center px-0.5 text-base-content/50">{{ $d }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($months as $month)
                        <tr>
                            <td class="font-medium whitespace-nowrap">{{ $month['label'] }}</td>
                            @foreach ($month['days'] as $day)
                                @if ($day === null)
                                    <td class="px-0.5"></td>
                                @else
                                    <td class="px-0.5 text-center align-middle
                                        {{ $day['holiday'] ? 'bg-error/10' : ($day['weekend'] ? 'bg-base-200' : '') }}">
                                        @if ($day['absence'])
                                            <span class="inline-block w-5 h-5 leading-5 rounded-sm text-xs {{ $toneBg[$day['absence']['tone']] }}"
                                                  title="{{ $day['absence']['label'] }}">{{ $day['absence']['short'] }}</span>
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-4 mt-3 text-sm">
            @foreach ($legend as $label => $tone)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm {{ $toneBg[$tone] }}"></span>{{ $label }}
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-base-200"></span>{{ __('Wochenende') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-error/10"></span>{{ __('Feiertag') }}
            </span>
        </div>
    </x-card>

    @if ($stats !== [])
        <x-card>
            <h3 class="font-semibold mb-2">{{ __('Statistik je Fehlgrund') }}</h3>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Fehlgrund') }}</x-table.th>
                        <x-table.th align="right">{{ __('Kalendertage') }}</x-table.th>
                        <x-table.th align="right">{{ __('Effektive Arbeitstage') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($stats as $label => $stat)
                    <tr>
                        <td>
                            <span class="inline-block w-3 h-3 rounded-sm align-middle mr-1.5 {{ $toneBg[$stat['tone']] }}"></span>{{ $label }}
                        </td>
                        <td class="text-right tabular-nums">{{ $stat['calendar'] }}</td>
                        <td class="text-right tabular-nums">{{ number_format($stat['effective'], 2) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
