@extends('layouts.app')
@section('title', __('manufacturing.order.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('manufacturing.order.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('manufacturing.order.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="account_tree" size="sm" :href="route('manufacturing-planning.index')" show-label>{{ __('manufacturing.planning.title') }}</x-icon-btn>
        @can('create', App\Models\ManufacturingOrder::class)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('manufacturing-orders.create')" show-label>{{ __('manufacturing.order.action.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    {{-- Tab-Strip über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav :items="collect([['label' => __('Alle'), 'route' => 'manufacturing-orders.index', 'active' => $status === 'all']])
        ->concat(collect($statuses)->map(fn($st) => [
            'label' => $st->label(),
            'route' => 'manufacturing-orders.index',
            'params' => ['status' => $st->value],
            'active' => $status === $st->value,
        ]))->all()" />

    @if ($orders->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">precision_manufacturing</span>'
                       :title="__('manufacturing.order.empty')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Nr.') }}</th>
                        <th>{{ __('Artikel') }}</th>
                        <th class="text-right">{{ __('manufacturing.order.field.target_qty') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('manufacturing-orders.show', $order) }}" class="link link-hover font-mono">{{ $order->number ?? '—' }}</a></td>
                        <td>{{ $order->article?->name }}{{ $order->variant ? ' — ' . ($order->variant->name ?? $order->variant->option_signature) : '' }}</td>
                        <td class="text-right tabular-nums">{{ $order->target_qty }} {{ $order->unit }}</td>
                        <td><span class="badge badge-sm">{{ $order->status->label() }}</span></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$orders" standing />
    @endif
</x-index-page>
@endsection
