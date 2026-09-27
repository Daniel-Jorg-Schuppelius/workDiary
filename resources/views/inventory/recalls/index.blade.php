{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rückrufaktionen (MVP-921). Erwartet: $recalls, $activeCount --}}
@extends('layouts.app')

@section('title', __('recall.title'))
@section('nav-title', __('recall.title'))

@section('content')
<x-index-page :subtitle="__('recall.subtitle')">
    <x-slot:actions>
        @can('create', \App\Models\Inventory\Recall::class)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('recalls.create')" show-label>{{ __('recall.action.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('recall.kpi.active')" :value="$activeCount" />
    </div>

    <x-filter-bar :action="route('recalls.index')" :reset="route('recalls.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('recall.field.status') }}">
            <option value="">{{ __('recall.filter.all_status') }}</option>
            @foreach (\App\Enums\Inventory\RecallStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('recall.field.number') }}</th>
                    <th>{{ __('recall.field.title') }}</th>
                    <th>{{ __('recall.field.variant') }}</th>
                    <th>{{ __('recall.field.kind') }}</th>
                    <th class="text-right">{{ __('recall.field.open_items') }}</th>
                    <th>{{ __('recall.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($recalls as $recall)
                <tr>
                    <td><a href="{{ route('recalls.show', $recall) }}" class="link font-mono">{{ $recall->number }}</a></td>
                    <td>{{ $recall->title }}</td>
                    <td>{{ $recall->variant?->article?->name }}{{ $recall->variant?->sku ? ' · ' . $recall->variant->sku : '' }}</td>
                    <td>{{ $recall->kind->label() }}</td>
                    <td class="text-right tabular-nums">{{ $recall->open_items_count }} / {{ $recall->items_count }}</td>
                    <td><x-status-badge size="md" outline :tone="$recall->status->tone()">{{ $recall->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('recalls.show', $recall)" :label="__('recall.action.show')" /></td>
                </tr>
            @empty
                <x-table.empty icon="campaign" :colspan="7" :title="__('recall.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$recalls" standing />
</x-index-page>
@endsection
