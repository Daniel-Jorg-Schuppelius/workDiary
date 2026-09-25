{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfmittelrunden (MVP-899). --}}
@extends('layouts.app')

@section('title', __('inspection_round.title'))
@section('nav-title', __('inspection_round.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('inspection_round.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('asset-compliance.index')" :label="__('Prüfmittel')" />
            @if ($canInspect)
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('asset-compliance.rounds.create')" show-label>{{ __('inspection_round.open') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('inspection_round.name') }}</th>
                    <th>{{ __('inspection_round.due_until') }}</th>
                    <th class="text-right">{{ __('inspection_round.progress') }}</th>
                    <th>{{ __('inspection_round.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($rounds as $round)
                <tr class="hover">
                    <td class="font-medium">{{ $round->name }}</td>
                    <td>{{ \App\Support\CarbonFmt::fdate($round->due_until) }}</td>
                    <td class="text-right tabular-nums">{{ $round->done_count }} / {{ $round->items_count }}</td>
                    <td><x-status-badge :tone="$round->status->value === 'open' ? 'info' : 'ghost'" size="sm">{{ $round->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="open_in_new" tone="outline" size="xs" :href="route('asset-compliance.rounds.show', $round)" :label="__('inspection_round.show')" /></td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('inspection_round.none_title')" :message="__('inspection_round.none')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$rounds" standing />
    </x-index-page>
@endsection
