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
@include('partials.page-fill')

@section('content')
{{-- Erwartet: $warehouse (Warehouse), $bins (Paginator<WarehouseBin> mit movements_count), $sort, $dir --}}
<x-index-page overflow="clip" :subtitle="__('inventory.subtitle.bins', ['warehouse' => $warehouse->name])" :badge="$warehouse->code" badge-tone="ghost"
              back-route="warehouses.index" :back-label="__('inventory.warehouses')">
    <x-slot:actions>
        @can('update', $warehouse)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('warehouses.bins.create', $warehouse)" show-label>{{ __('inventory.action.create_bin') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @if ($bins->total() === 0)
        <x-empty-state framed icon="shelves"
                       :title="__('inventory.empty.bins')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('warehouses.bins.index', $warehouse)"
                 :current-sort="$sort"
                 :current-dir="$dir">
            <x-slot:head>
                <tr>
                    <x-table.th sort="sort_order" default class="w-20">{{ __('inventory.field.sort_order') }}</x-table.th>
                    <x-table.th sort="code">{{ __('inventory.field.code') }}</x-table.th>
                    <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                    <x-table.th sort="movements" align="right">{{ __('inventory.field.movement') }}</x-table.th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($bins as $bin)
                <tr class="hover">
                    <td class="tabular-nums">{{ $bin->sort_order }}</td>
                    <td class="font-mono text-sm font-medium">{{ $bin->code }}</td>
                    <td>{{ $bin->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $bin->movements_count }}</td>
                    <td>
                        @if ($bin->blocked)
                            <x-status-badge tone="warning">{{ __('inventory.state.blocked') }}</x-status-badge>
                        @elseif ($bin->active)
                            <x-status-badge tone="success">{{ __('article.status.active') }}</x-status-badge>
                        @else
                            <x-status-badge>{{ __('article.status.retired') }}</x-status-badge>
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

    <x-pagination :paginator="$bins" standing />
</x-index-page>
@endsection
