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
            <select id="sug-wh" name="warehouse" class="select select-sm select-bordered" onchange="this.form.submit()">
                @foreach ($warehouses as $wh)
                    <option value="{{ $wh->sqid }}" @selected($warehouse && $warehouse->id === $wh->id)>{{ $wh->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if (empty($suggestions))
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>'
                       :title="__('procurement.ui.none')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
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
        </x-card>

        @if ($warehouse)
            <form method="POST" action="{{ route('purchase-orders.suggestions.apply') }}" class="mt-3 self-end">
                @csrf
                <input type="hidden" name="warehouse" value="{{ $warehouse->sqid }}">
                <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('procurement.action.apply') }}</x-icon-btn>
            </form>
        @endif
    @endif
</x-index-page>
@endsection
