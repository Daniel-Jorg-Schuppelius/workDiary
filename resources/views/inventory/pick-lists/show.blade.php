{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.pick_list.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.pick_list.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
{{-- Erwartet: $list (PickList), $sourceSlug, $sourceSqid, $source (Model) --}}
<x-index-page overflow="clip" :subtitle="__('inventory.pick_list.subtitle')" :badge="$list->sourceLabel()" badge-tone="ghost">
    <x-slot:actions>
        @if ($source instanceof \App\Models\ManufacturingOrder)
            <x-icon-btn icon="arrow_back" size="sm" :href="route('manufacturing-orders.show', $source)" show-label>{{ __('inventory.pick_list.source') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="picture_as_pdf" tone="primary" size="sm" target="_blank"
                    :href="route('inventory.pick-lists.pdf', ['source' => $sourceSlug, 'sqid' => $sourceSqid])" show-label>{{ __('inventory.action.pick_list_pdf') }}</x-icon-btn>
    </x-slot:actions>

    @if ($list->isEmpty())
        <x-empty-state framed icon="checklist"
                       :title="__('inventory.empty.pick_list')" />
    @else
        <x-table :zebra="true" scroll="flex" :pinRows="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="number" class="w-14">{{ __('inventory.pick_list.position') }}</x-table.th>
                    <x-table.th sort>{{ __('inventory.field.warehouse') }}</x-table.th>
                    <x-table.th sort>{{ __('inventory.field.bin') }}</x-table.th>
                    <x-table.th sort>{{ __('inventory.field.lot') }}</x-table.th>
                    <x-table.th sort>{{ __('inventory.field.variant') }}</x-table.th>
                    <x-table.th sort>{{ __('article.field.sku') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('inventory.field.quantity') }}</x-table.th>
                    <th>{{ __('inventory.field.unit') }}</th>
                    <x-table.th sort type="number" align="right">{{ __('inventory.pick_list.available') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($list->lines as $i => $line)
                <tr>
                    <td class="tabular-nums">{{ $i + 1 }}</td>
                    <td>{{ $line->warehouse->name }}</td>
                    <td class="font-mono text-sm">{{ $line->bin?->code ?? '—' }}</td>
                    <td class="font-mono text-sm">
                        {{ $line->lot?->lot_no ?? '—' }}
                        @if ($line->lot?->best_before)<span class="text-xs text-muted ml-1">{{ $line->lot->best_before->format('d.m.Y') }}</span>@endif
                    </td>
                    <td class="font-medium">{{ $line->label() }}</td>
                    <td class="font-mono text-sm">{{ $line->sku() !== '' ? $line->sku() : '—' }}</td>
                    <td class="text-right tabular-nums font-medium">{{ $line->qty }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="text-right tabular-nums {{ $line->isShort() ? 'text-warning' : '' }}">{{ $line->available }}</td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
