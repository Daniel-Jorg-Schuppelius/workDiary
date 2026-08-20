{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('manufacturing.capacity.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('manufacturing.capacity.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('manufacturing.capacity.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('work-centers.create')" show-label>{{ __('manufacturing.capacity.add') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-slot:note>
        {{ __('manufacturing.capacity.period_note', ['from' => $from->isoFormat('L'), 'to' => $to->isoFormat('L')]) }}
    </x-slot:note>

    @if ($board->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">precision_manufacturing</span>'
                       :title="__('manufacturing.capacity.empty')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('manufacturing.capacity.work_center') }}</th>
                    <th class="text-right">{{ __('manufacturing.capacity.capacity') }}</th>
                    <th class="text-right">{{ __('manufacturing.capacity.planned') }}</th>
                    <th class="text-right">{{ __('manufacturing.capacity.free') }}</th>
                    <th class="text-right">{{ __('manufacturing.capacity.utilization') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($board as $row)
                <tr class="{{ $row['load']['overloaded'] ? 'bg-error/10' : '' }}">
                    <td>{{ $row['center']->name }}</td>
                    <td class="text-right tabular-nums">{{ $row['load']['capacity'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['load']['planned'] }}</td>
                    <td class="text-right tabular-nums {{ $row['load']['free'] < 0 ? 'text-error font-semibold' : '' }}">{{ $row['load']['free'] }}</td>
                    <td class="text-right tabular-nums">{{ round($row['load']['utilization'] * 100) }}%</td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
