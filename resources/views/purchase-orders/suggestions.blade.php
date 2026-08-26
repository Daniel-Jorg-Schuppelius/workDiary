{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : suggestions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.ui.suggestions_title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.ui.suggestions_title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('procurement.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('purchase-orders.index')" show-label>{{ __('procurement.title') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('purchase-orders.suggestions')">
        <x-filter-field :label="__('procurement.ui.select_warehouse')" for="sug-wh">
            <select id="sug-wh" name="warehouse" class="select select-sm select-bordered" data-autosubmit>
                @foreach ($warehouses as $wh)
                    <option value="{{ $wh->sqid }}" @selected($warehouse && $warehouse->id === $wh->id)>{{ $wh->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if (empty($suggestions))
        <x-empty-state framed icon="local_shipping"
                       :title="__('procurement.ui.none')" />
    @else
        {{-- Übernehmen-Aktion VOR der Voll-Höhe-Tabelle (scroll=flex):
             darunter läge sie unterm Fold (Vollscan 2026-08 I10). --}}
        @if ($warehouse)
            <form method="POST" action="{{ route('purchase-orders.suggestions.apply') }}" class="flex flex-none justify-end">
                @csrf
                <input type="hidden" name="warehouse" value="{{ $warehouse->sqid }}">
                <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('procurement.action.apply') }}</x-icon-btn>
            </form>
        @endif

        <x-table :zebra="true" scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.field.article') }}</th>
                    <th>{{ __('procurement.field.supplier') }}</th>
                    <th class="text-right">{{ __('procurement.ui.needed') }}</th>
                    <th class="text-right">{{ __('procurement.ui.suggested') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($suggestions as $s)
                <tr>
                    <td>{{ $s['article']->name }}</td>
                    <td>{{ $s['supply']?->supplier?->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $s['needed'] }}</td>
                    <td class="text-right tabular-nums">{{ $s['suggested'] }}</td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
