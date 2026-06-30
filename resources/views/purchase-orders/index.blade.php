@extends('layouts.app')
@section('title', __('procurement.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('procurement.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="local_shipping" size="sm" :href="route('purchase-orders.incoming')" show-label>{{ __('procurement.action.incoming') }}</x-icon-btn>
        <x-icon-btn icon="lightbulb" size="sm" :href="route('purchase-orders.suggestions')" show-label>{{ __('procurement.action.suggestions') }}</x-icon-btn>
        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                    :href="route('purchase-orders.create')" show-label>{{ __('procurement.action.create') }}</x-icon-btn>
    </x-slot:actions>

    <div role="tablist" class="tabs tabs-box w-full">
        <a role="tab" href="{{ route('purchase-orders.index') }}" class="tab {{ $status === 'all' ? 'tab-active' : '' }}">{{ __('Alle') }}</a>
        @foreach ($statuses as $st)
            <a role="tab" href="{{ route('purchase-orders.index', ['status' => $st->value]) }}"
               class="tab {{ $status === $st->value ? 'tab-active' : '' }}">{{ $st->label() }}</a>
        @endforeach
    </div>

    @if ($orders->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>'
                       :title="__('procurement.empty')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('procurement.field.number') }}</th>
                        <th>{{ __('procurement.field.supplier') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($orders as $order)
                    <tr>
                        <td><a href="{{ route('purchase-orders.show', $order) }}" class="link link-hover font-mono">{{ $order->number }}</a></td>
                        <td>{{ $order->supplier?->name }}</td>
                        <td><span class="badge badge-sm badge-ghost">{{ $order->status->label() }}</span></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$orders" standing />
    @endif
</x-index-page>
@endsection
