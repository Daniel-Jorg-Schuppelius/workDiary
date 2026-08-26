{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.bins') . ' — ' . $warehouse->name . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.bins'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
{{-- Erwartet: $warehouse (Warehouse), $bins (Collection<WarehouseBin> mit movements_count) --}}
<x-index-page overflow="clip" :subtitle="__('inventory.subtitle.bins', ['warehouse' => $warehouse->name])" :badge="$warehouse->code" badge-tone="ghost">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('warehouses.index')" show-label>{{ __('inventory.warehouses') }}</x-icon-btn>
        @can('update', $warehouse)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('warehouses.bins.create', $warehouse)" show-label>{{ __('inventory.action.create_bin') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @if ($bins->isEmpty())
        <x-empty-state framed icon="shelves"
                       :title="__('inventory.empty.bins')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="number" default="asc" class="w-20">{{ __('inventory.field.sort_order') }}</x-table.th>
                    <x-table.th sort>{{ __('inventory.field.code') }}</x-table.th>
                    <x-table.th sort>{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('inventory.field.movement') }}</x-table.th>
                    <x-table.th sort>{{ __('Status') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($bins as $bin)
                <tr>
                    <td class="tabular-nums">{{ $bin->sort_order }}</td>
                    <td class="font-mono text-sm font-medium">{{ $bin->code }}</td>
                    <td>{{ $bin->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $bin->movements_count }}</td>
                    <td>
                        @if ($bin->blocked)
                            <span class="badge badge-sm badge-warning">{{ __('inventory.state.blocked') }}</span>
                        @elseif ($bin->active)
                            <span class="badge badge-sm badge-success">{{ __('article.status.active') }}</span>
                        @else
                            <span class="badge badge-sm badge-ghost">{{ __('article.status.retired') }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @can('update', $warehouse)
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                            :href="route('warehouses.bins.edit', [$warehouse, $bin])" :title="__('Bearbeiten')" />
                                <x-action-form :action="route('warehouses.bins.block', [$warehouse, $bin])" method="POST">
                                    <x-icon-btn :icon="$bin->blocked ? 'lock_open' : 'lock'" size="xs" type="submit"
                                                :title="$bin->blocked ? __('inventory.action.unblock_bin') : __('inventory.action.block_bin')" />
                                </x-action-form>
                                <x-action-form :action="route('warehouses.bins.destroy', [$warehouse, $bin])" method="DELETE" :confirm="__('inventory.confirm.delete_bin')">
                                    <x-icon-btn icon="delete" size="xs" type="submit" tone="error" :title="__('Löschen')" />
                                </x-action-form>
                            </div>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
