{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Zentraler Arbeitsvorrat (Feature 179). Variablen: $items (Paginator von WorkItem), $sources (Schlüssel → Quelle), $now, $filters (category, source, due, mine, dormant), $sort, $dir, $dormantDays (Schwelle „ruhend“, MVP-1670), $canConfigure. --}}

@extends('layouts.app')

@section('title', __('work-queue.title'))
@section('nav-title', __('work-queue.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('work-queue.subtitle')">
    @if ($canConfigure)
        <x-slot:actions>
            <x-icon-btn icon="tune" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('work-queue.settings.edit') . '?dialog=1'"
                        show-label>{{ __('work-queue.settings.title') }}</x-icon-btn>
        </x-slot:actions>
    @endif
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

        <select name="due" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('work-queue.filter.due') }}">
            <option value="">{{ __('work-queue.filter.all_due') }}</option>
            @foreach (\App\Enums\WorkQueue\WorkItemDueFilter::cases() as $due)
                <option value="{{ $due->value }}" @selected($filters['due'] === $due->value)>{{ $due->label() }}</option>
            @endforeach
        </select>

        <x-filter-toggle name="mine" :label="__('work-queue.filter.mine')" :checked="$filters['mine']" />
        {{-- Liegezeit (MVP-1670): nur Vorgänge ab der Schwelle der Organisation. --}}
        <x-filter-toggle name="dormant" :label="__('work-queue.filter.dormant', ['days' => $dormantDays])" :checked="$filters['dormant']" />
    </x-filter-bar>

    @if ($overflow > 0)
        <div role="status" class="alert alert-info text-sm mb-2"><span>{{ trans_choice('work-queue.overflow', $overflow, ['count' => $overflow, 'limit' => \App\Services\WorkQueue\WorkQueue::SOURCE_LIMIT]) }}</span></div>
    @endif
    @if ($items->isEmpty())
        <x-empty-state framed icon="task_alt" :title="__('work-queue.empty.title')" :message="__('work-queue.empty.message')" />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('work-queue.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['category' => $filters['category'] ?: null, 'source' => $filters['source'] ?: null, 'due' => $filters['due'] ?: null, 'mine' => $filters['mine'] ? 1 : null, 'dormant' => $filters['dormant'] ? 1 : null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="urgency">{{ __('work-queue.column.urgency') }}</x-table.th>
                    <x-table.th sort="title">{{ __('work-queue.column.item') }}</x-table.th>
                    <x-table.th sort="source">{{ __('work-queue.column.source') }}</x-table.th>
                    <x-table.th sort="due">{{ __('work-queue.column.due') }}</x-table.th>
                    <x-table.th sort="idle">{{ __('work-queue.column.idle') }}</x-table.th>
                    <th class="text-right">{{ __('work-queue.column.count') }}</th>
                    <th>{{ __('work-queue.column.assignment') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($items as $item)
                @php
                    $source = $sources[$item->source];
                    $urgency = $item->urgency($now);
                    $idleDays = $item->idleDays($now);
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
                    <td class="whitespace-nowrap text-sm tabular-nums" @if ($item->movedAt !== null) title="{{ __('work-queue.idle.moved_at', ['date' => $item->movedAt->fdatetime()]) }}" @endif>
                        @if ($idleDays === null)
                            <span class="text-muted">—</span>
                        @else
                            {{ trans_choice('work-queue.idle.days', $idleDays, ['count' => $idleDays]) }}
                            @if ($idleDays >= $dormantDays)
                                <x-status-badge size="sm" tone="warning">{{ __('work-queue.idle.dormant') }}</x-status-badge>
                            @endif
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $item->count > 1 ? $item->count : '' }}</td>
                    <td class="text-sm text-muted">
                        @if ($item->onBehalfOf !== null)
                            {{-- Vertretung (Feature 202): zugewiesen an die vertretene Person. --}}
                            <x-status-badge size="sm" tone="info">{{ __('work-queue.assignment.on_behalf_of', ['name' => $item->onBehalfOf]) }}</x-status-badge>
                        @else
                            {{ $item->personal ? __('work-queue.assignment.personal') : __('work-queue.assignment.shared') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$items" standing />
    @endif
</x-index-page>
@endsection
