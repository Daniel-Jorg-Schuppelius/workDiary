{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gefährdungskatalog der Organisation (Feature 132, MVP-1002). Variablen: $items (Paginator), $sort, $dir, $canManage
--}}
@extends('layouts.app')
@section('title', __('safety.catalog.title'))
@section('nav-title', __('safety.catalog.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('safety.catalog.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="health_and_safety" size="sm" show-label :href="route('safety.assessments.index')">{{ __('safety.register.nav.assessments') }}</x-icon-btn>
        @if ($canManage)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger
                        :href="route('safety.hazard-catalog.create')" :label="__('safety.catalog.action.add')" />
        @endif
    </x-slot:actions>

    <x-table scroll="flex" table-sort="server"
             :route="route('safety.hazard-catalog.index')"
             :current-sort="$sort"
             :current-dir="$dir">
        <x-slot:head>
            <tr>
                <x-table.th sort="category">{{ __('safety.catalog.field.category') }}</x-table.th>
                <x-table.th sort="hazard">{{ __('safety.catalog.field.hazard') }}</x-table.th>
                <x-table.th sort="risk" align="right">{{ __('safety.catalog.field.risk') }}</x-table.th>
                <x-table.th sort="source">{{ __('safety.catalog.field.source') }}</x-table.th>
                <x-table.th sort="is_active" default>{{ __('safety.catalog.field.is_active') }}</x-table.th>
                <th class="text-right"></th>
            </tr>
        </x-slot:head>
        @forelse ($items as $item)
            <tr class="hover">
                <td>{{ $item->category }}</td>
                <td>
                    <div class="font-medium">{{ $item->hazard }}</div>
                    @if ($item->measure)
                        <div class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($item->measure, 120) }}</div>
                    @endif
                </td>
                <td class="text-right">
                    <x-status-badge :tone="\App\Models\Safety\HazardAssessmentItem::riskTone($item->severity * $item->likelihood)" size="sm">{{ $item->severity * $item->likelihood }}</x-status-badge>
                </td>
                <td class="text-sm text-muted">{{ $item->source_profile ?? __('safety.catalog.own') }}</td>
                <td><x-status-badge :tone="$item->is_active ? 'success' : 'ghost'" size="sm">{{ $item->is_active ? __('Aktiv') : __('Inaktiv') }}</x-status-badge></td>
                <td class="text-right whitespace-nowrap">
                    @if ($canManage)
                        <x-icon-btn icon="edit" size="sm" tone="ghost" data-entry-modal-trigger
                                    :href="route('safety.hazard-catalog.edit', $item)" :label="__('safety.catalog.action.edit')" />
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" :title="__('safety.catalog.empty')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$items" standing />
</x-index-page>
@endsection
