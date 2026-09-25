{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfmittelrunde (MVP-899): Scan öffnet die Schnellerfassung; Offene, Überfällige und Erledigte sichtbar. --}}
@extends('layouts.app')

@section('title', $round->name)
@section('nav-title', __('inspection_round.title'))

@php
    $done = $items->filter->isDone()->count();
    $overdue = $items->filter->isOverdue()->count();
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$round->name" :badge="$round->status->label()" badgeTone="ghost"
                        :subtitle="__('inspection_round.due_until') . ': ' . \App\Support\CarbonFmt::fdate($round->due_until)">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('asset-compliance.rounds.index')" :label="__('inspection_round.title')" />
                @if ($canInspect)
                    <x-action-form :action="route('asset-compliance.rounds.close', $round)" :confirm="__('inspection_round.confirm_close', ['missing' => $items->count() - $done])" confirm-icon="task_alt">
                        <x-icon-btn icon="task_alt" size="sm" type="submit" show-label>{{ __('inspection_round.close') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('inspection_round.kpi_done')" :value="$done . ' / ' . $items->count()" />
        <x-kpi-tile :label="__('inspection_round.kpi_missing')" :value="$items->count() - $done" />
        <x-kpi-tile :label="__('inspection_round.kpi_overdue')" :value="$overdue" />
    </div>

    @if ($canInspect)
        <x-card>
            <form method="POST" action="{{ route('asset-compliance.rounds.scan', $round) }}" class="flex items-end gap-2">
                @csrf
                <div class="fieldset grow"><label for="code" class="fieldset-label">{{ __('inspection_round.scan') }}</label>
                    <input id="code" name="code" autofocus required autocomplete="off" enterkeyhint="send" class="input input-bordered w-full font-mono" placeholder="QR / Anlagen-Nr. / Inventar-Nr. / SN"></div>
                <x-button type="submit" tone="primary">{{ __('inspection_round.scan_submit') }}</x-button>
            </form>
            @if ($matches->isNotEmpty())
                <div class="mt-3 space-y-1">
                    <p class="text-sm">{{ __('inspection_round.scan_several') }}</p>
                    @foreach ($matches as $match)
                        <x-icon-btn icon="fact_check" size="sm" :href="route('asset-compliance.rounds.capture', [$round, $match])" show-label>{{ $match->assignment?->profile?->name }}</x-icon-btn>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('inspection_round.asset') }}</th>
                    <th>{{ __('inspection_round.profile') }}</th>
                    <th>{{ __('inspection_round.due_on') }}</th>
                    <th>{{ __('inspection_round.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($items as $item)
                <tr>
                    <td><span class="font-medium">{{ $item->asset?->name }}</span> <span class="text-xs text-muted">{{ $item->asset?->asset_no }}</span></td>
                    <td>{{ $item->assignment?->profile?->name }}</td>
                    <td>{{ $item->due_on !== null ? \App\Support\CarbonFmt::fdate($item->due_on) : '—' }}</td>
                    <td>
                        @if ($item->isDone())
                            <x-status-badge tone="success" size="sm">{{ $item->event?->result?->label() }}</x-status-badge>
                        @elseif ($item->isOverdue())
                            <x-status-badge tone="error" size="sm">{{ __('inspection_round.overdue') }}</x-status-badge>
                        @else
                            <x-status-badge tone="warning" size="sm">{{ __('inspection_round.pending') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($canInspect && ! $item->isDone())
                            <x-icon-btn icon="fact_check" tone="outline" size="xs" :href="route('asset-compliance.rounds.capture', [$round, $item])" :label="__('inspection_round.capture')" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</x-page-shell>
@endsection
