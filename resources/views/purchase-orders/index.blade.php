{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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

    {{-- Tab-Strip über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav :items="collect([['label' => __('Alle'), 'route' => 'purchase-orders.index', 'active' => $status === 'all']])
        ->concat(collect($statuses)->map(fn($st) => [
            'label' => $st->label(),
            'route' => 'purchase-orders.index',
            'params' => ['status' => $st->value],
            'active' => $status === $st->value,
        ]))->all()" />

    @if ($orders->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>'
                       :title="__('procurement.empty')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true">
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
        <x-pagination :paginator="$orders" standing />
    @endif
</x-index-page>
@endsection
