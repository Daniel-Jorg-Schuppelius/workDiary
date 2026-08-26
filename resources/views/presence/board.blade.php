{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : board.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Aktuelle Personal-Belegung (MVP-524): Alltagssicht ohne Fehlgründe.
--}}

@extends('layouts.app')
@section('title', __('Aktuelle Belegung'))
@section('nav-title', __('Aktuelle Belegung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Wer ist gerade im Haus? Stand: :time', ['time' => $snapshot['at']->setTimezone(\App\Support\Tz::current())->format('H:i')])" />
    </x-slot:toolbar>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
        <x-kpi-tile :label="__('Anwesend')" :value="count($snapshot['present']) + count($snapshot['present_unmapped'])" format="int" tone="success" />
        <x-kpi-tile :label="__('Außer Haus')" :value="count($snapshot['off_site'])" format="int" tone="info" />
        <x-kpi-tile :label="__('Abwesend')" :value="count($snapshot['absent'])" format="int" tone="neutral" />
        <x-kpi-tile :label="__('Nicht eingestempelt')" :value="count($snapshot['unaccounted'])" format="int" tone="warning" />
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        <x-card>
            <h3 class="font-semibold mb-2 inline-flex items-center gap-1.5">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-success"></span>{{ __('Anwesend') }}
            </h3>
            <ul class="divide-y divide-base-200">
                @forelse ([...$snapshot['present'], ...$snapshot['present_unmapped']] as $row)
                    <li class="py-1.5 flex items-center gap-2">
                        <span class="font-medium">{{ $row['user']->name }}</span>
                        @if ($row['on_break'])
                            <x-status-badge tone="warning" size="sm">{{ __('Pause') }}</x-status-badge>
                        @endif
                        <span class="ml-auto text-sm text-muted tabular-nums">
                            @if ($row['since'])
                                {{ __('seit') }} {{ $row['since']->setTimezone(\App\Support\Tz::current())->format('H:i') }}
                            @endif
                            @if (isset($returns[(int) $row['user']->id]))
                                · {{ $returns[(int) $row['user']->id] }}
                            @endif
                            @if ($row['site_name'])
                                · {{ $row['site_name'] }}
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="py-1.5 text-muted">{{ __('Niemand eingestempelt.') }}</li>
                @endforelse
            </ul>
        </x-card>

        <div class="space-y-3">
            <x-card>
                <h3 class="font-semibold mb-2 inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-info"></span>{{ __('Außer Haus / Einsatz') }}
                </h3>
                <ul class="divide-y divide-base-200">
                    @forelse ($snapshot['off_site'] as $row)
                        <li class="py-1.5 flex items-center gap-2">
                            <span class="font-medium">{{ $row['user']->name }}</span>
                            <span class="ml-auto text-sm text-muted">{{ $row['context'] ?? '' }}</span>
                        </li>
                    @empty
                        <li class="py-1.5 text-muted">{{ __('Niemand im Außeneinsatz.') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card>
                <h3 class="font-semibold mb-2 inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-base-content/40"></span>{{ __('Abwesend') }}
                </h3>
                {{-- Datenschutz: Fehlgründe werden hier bewusst NICHT angezeigt —
                     die planmäßige Rückkehr (MVP-527) ist nur eine Zeitangabe. --}}
                <ul class="divide-y divide-base-200">
                    @forelse ($snapshot['absent'] as $row)
                        <li class="py-1.5 flex items-center gap-2">
                            <span>{{ $row['user']->name }}</span>
                            @if (isset($returns[(int) $row['user']->id]))
                                <span class="ml-auto text-sm text-muted tabular-nums">{{ $returns[(int) $row['user']->id] }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="py-1.5 text-muted">{{ __('Keine ganztägigen Abwesenheiten.') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
