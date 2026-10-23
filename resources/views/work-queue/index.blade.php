{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Zentraler Arbeitsvorrat (Feature 179). Variablen: $items (Paginator von WorkItem), $sources (Schlüssel → Quelle), $now, $filters, $sort, $dir. --}}

@extends('layouts.app')

@section('title', __('work-queue.title'))
@section('nav-title', __('work-queue.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('work-queue.subtitle')">
    <x-filter-bar :action="route('work-queue.index')" :reset="route('work-queue.index')">
        <select name="category" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('work-queue.filter.category') }}">
            <option value="">{{ __('work-queue.filter.all_categories') }}</option>
            @foreach (\App\Enums\WorkQueue\WorkItemCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected($filters['category'] === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </select>

        <select name="source" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('work-queue.filter.source') }}">
            <option value="">{{ __('work-queue.filter.all_sources') }}</option>
            @foreach ($sources as $key => $source)
                <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $source->label() }}</option>
            @endforeach
        </select>

        <x-filter-toggle name="mine" :label="__('work-queue.filter.mine')" :checked="$filters['mine']" />
    </x-filter-bar>

    @if ($items->isEmpty())
        <x-empty-state framed icon="task_alt" :title="__('work-queue.empty.title')" :message="__('work-queue.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('work-queue.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['category' => $filters['category'] ?: null, 'source' => $filters['source'] ?: null, 'mine' => $filters['mine'] ? 1 : null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="urgency">{{ __('work-queue.column.urgency') }}</x-table.th>
                    <x-table.th sort="title">{{ __('work-queue.column.item') }}</x-table.th>
                    <x-table.th sort="source">{{ __('work-queue.column.source') }}</x-table.th>
                    <x-table.th sort="due">{{ __('work-queue.column.due') }}</x-table.th>
                    <th class="text-right">{{ __('work-queue.column.count') }}</th>
                    <th>{{ __('work-queue.column.assignment') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($items as $item)
                @php
                    $source = $sources[$item->source];
                    $urgency = $item->urgency($now);
                @endphp
                <tr class="hover">
                    <td><x-status-badge :tone="$urgency->tone()" size="sm">{{ $urgency->label() }}</x-status-badge></td>
                    <td>
                        @if ($item->url !== null)
                            <a href="{{ $item->url }}" class="link link-hover font-medium">{{ $item->title }}</a>
                        @else
                            <span class="font-medium">{{ $item->title }}</span>
                        @endif
                        @if ($item->detail !== null)
                            <div class="text-xs text-muted">{{ $item->detail }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        <x-icon :name="$source->category()->icon()" class="align-middle text-muted" />
                        {{ $source->label() }}
                    </td>
                    <td class="whitespace-nowrap text-sm tabular-nums">{{ $item->dueAt?->fdate() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $item->count > 1 ? $item->count : '' }}</td>
                    <td class="text-sm text-muted">{{ $item->personal ? __('work-queue.assignment.personal') : __('work-queue.assignment.shared') }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$items" standing />
    @endif
</x-index-page>
@endsection
