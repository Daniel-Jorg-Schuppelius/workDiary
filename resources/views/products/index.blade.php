{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('products.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('products.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $products */
    $search = $search ?? '';
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('products.title.subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('products.create')"
                        show-label>{{ __('products.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('products.index')" :reset="$search !== '' ? route('products.index') : null">
        <x-filter-field :label="__('Suche')" for="prod-q" class="flex-1 min-w-60">
            <input id="prod-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
    </x-filter-bar>

    @if ($products->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>'
                       :title="$search !== '' ? __('products.title.empty_search', ['q' => $search]) : __('products.title.empty')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('products.field.manufacturer') }}</th>
                    <th>{{ __('products.field.model') }}</th>
                    <th>{{ __('products.field.name') }}</th>
                    <th>{{ __('products.field.product_group') }}</th>
                    <th class="text-right">{{ __('products.field.articles') }}</th>
                    <th class="text-right">{{ __('products.field.assets') }}</th>
                    <th>{{ __('products.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($products as $product)
                @php /** @var \App\Models\Product $product */ @endphp
                <tr>
                    <td>{{ $product->manufacturer }}</td>
                    <td class="font-mono text-sm">{{ $product->model }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->productGroupClassification?->label ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $product->articles_count }}</td>
                    <td class="text-right tabular-nums">{{ $product->assets_count }}</td>
                    <td><x-status-badge size="xs" :tone="$product->status->tone()">{{ $product->status->label() }}</x-status-badge></td>
                    <td class="text-right">
                        @if ($canManage ?? false)
                            <x-icon-btn icon="edit" tone="ghost" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('products.edit', $product)"
                                        :label="__('products.action.edit')" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$products" standing />
    @endif
</x-index-page>
@endsection
