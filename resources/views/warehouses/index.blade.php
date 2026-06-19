@extends('layouts.app')
@section('title', __('inventory.warehouses') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.warehouses'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.subtitle.warehouses')">
    <x-slot:actions>
        <x-icon-btn icon="warehouse" size="sm" :href="route('inventory.stock')" show-label>{{ __('inventory.stock') }}</x-icon-btn>
        @can('create', App\Models\Warehouse::class)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('warehouses.create')" show-label>{{ __('inventory.action.create_warehouse') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @if ($warehouses->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">warehouse</span>'
                       :title="__('inventory.empty.warehouses')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('inventory.field.code') }}</th>
                        <th>{{ __('inventory.field.movement') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($warehouses as $warehouse)
                    <tr>
                        <td class="font-medium">
                            <a href="{{ route('inventory.stock', ['warehouse' => $warehouse->sqid]) }}" class="link link-hover">{{ $warehouse->name }}</a>
                            @if ($warehouse->is_default)<span class="badge badge-sm badge-primary ml-2">{{ __('inventory.field.default') }}</span>@endif
                        </td>
                        <td class="font-mono text-sm">{{ $warehouse->code ?? '—' }}</td>
                        <td class="tabular-nums">{{ $warehouse->movements_count }}</td>
                        <td>
                            @if ($warehouse->blocked)
                                <span class="badge badge-sm badge-warning">{{ __('inventory.state.blocked') }}</span>
                            @elseif ($warehouse->active)
                                <span class="badge badge-sm badge-success">{{ __('article.status.active') }}</span>
                            @else
                                <span class="badge badge-sm badge-ghost">{{ __('article.status.retired') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('update', $warehouse)
                                <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                            :href="route('warehouses.edit', $warehouse)" :title="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $warehouse)
                                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" class="inline"
                                      onsubmit="return confirm('{{ __('inventory.confirm.delete_warehouse') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" size="xs" type="submit" tone="error" :title="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <div class="mt-3">{{ $warehouses->links() }}</div>
    @endif
</x-index-page>
@endsection
