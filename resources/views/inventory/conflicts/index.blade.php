{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.conflict.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.conflict.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.conflict.subtitle')">
    @include('inventory._tabs')

    {{-- Status-Reiter über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav class="w-fit my-3" :items="collect(['open', 'all'])->map(fn($tab) => [
        'label' => __('inventory.conflict.filter.' . $tab),
        'route' => 'inventory.conflicts.index',
        'params' => array_filter(['status' => $tab, 'type' => $filters['type']]),
        'active' => ($filters['status'] ?? 'open') === $tab,
    ])->all()" />

    <x-filter-bar :action="route('inventory.conflicts.index')" :reset="route('inventory.conflicts.index', ['status' => $filters['status']])">
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
        <select name="type" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('inventory.conflict.col.type') }}">
            <option value="">{{ __('inventory.conflict.filter.all_types') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __('inventory.conflict.type.' . $type) }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($conflicts->isEmpty())
        <x-empty-state framed :title="__('inventory.conflict.empty')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('inventory.conflict.col.type') }}</th>
                    <th>{{ __('inventory.conflict.col.id') }}</th>
                    <th>{{ __('inventory.conflict.col.operation') }}</th>
                    <th class="text-right">{{ __('inventory.conflict.col.qty') }}</th>
                    <th>{{ __('inventory.conflict.col.status') }}</th>
                    <th class="text-right">{{ __('inventory.conflict.col.actions') }}</th>
                </tr>
            </x-slot:head>

                @foreach ($conflicts as $conflict)
                    @php($snap = $conflict->local_snapshot ?? [])
                    @php($isArticle = $conflict->conflict_type === \App\Models\Integration\PendingExternalConflict::TYPE_ARTICLE)
                    <tr class="hover">
                        <td>
                            <div>{{ __('inventory.conflict.type.' . $conflict->conflict_type) }}</div>
                            <div class="text-xs opacity-60">{{ $pluginLabels[$conflict->plugin_id] ?? $conflict->plugin_id }}</div>
                        </td>
                        <td>
                            @if ($isArticle)
                                <div>{{ $snap['name'] ?? '—' }}</div>
                                <div class="text-xs opacity-60">{{ $snap['article_number'] ?? '' }}</div>
                            @else
                                <div class="font-mono text-xs">#{{ $conflict->referenceable_id }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($isArticle)
                                {{-- Beide Stände je abweichendem Feld, aus dem Schnappschuss des Konflikts. --}}
                                @foreach ($articleDiffs[$conflict->id] ?? [] as $diff)
                                    <div class="text-xs">
                                        <span class="font-medium">{{ $diff['label'] }}:</span>
                                        {{ __('inventory.conflict.diff', ['local' => $diff['local'], 'remote' => $diff['remote']]) }}
                                    </div>
                                @endforeach
                            @else
                                <div>{{ $snap['movement_type'] ?? $snap['operation'] ?? '—' }}</div>
                                <div class="text-xs opacity-60">{{ $snap['stock_state'] ?? '' }}</div>
                            @endif
                        </td>
                        <td class="text-right font-mono">{{ $isArticle ? '—' : ($snap['qty_base'] ?? '—') }}</td>
                        <td>
                            <x-status-badge :tone="$conflict->status->tone()">{{ $conflict->status->label() }}</x-status-badge>
                        </td>
                        <td class="text-right">
                            @if ($isArticle && $canResolveArticle && $conflict->isOpen())
                                {{-- Drei Wege je Artikelkonflikt (Entscheidung 2026-10-06): lokal behalten, Stand des Fremdsystems übernehmen, verwerfen. --}}
                                @php($system = $pluginLabels[$conflict->plugin_id] ?? $conflict->plugin_id)
                                @php($adoptLabel = __('inventory.conflict.action.adopt_remote', ['system' => $system]))
                                @php($adoptConfirm = __('inventory.conflict.confirm.adopt_remote', ['system' => $system]))
                                @php($keepConfirm = __('inventory.conflict.confirm.keep_local_article', ['system' => $system]))
                                <div class="flex justify-end gap-1">
                                    <x-action-form :action="route('inventory.conflicts.keep-local', $conflict)"
                                                   :confirm="$keepConfirm"
                                                   :confirm-label="__('inventory.conflict.action.keep_local')" confirm-icon="check">
                                        <x-icon-btn icon="check" size="xs" type="submit" :title="__('inventory.conflict.action.keep_local')" />
                                    </x-action-form>
                                    <x-action-form :action="route('inventory.conflicts.adopt-remote', $conflict)"
                                                   :confirm="$adoptConfirm" confirm-tone="warning"
                                                   :confirm-label="$adoptLabel" confirm-icon="cloud_download">
                                        <x-icon-btn icon="cloud_download" size="xs" tone="warning" type="submit" :title="$adoptLabel" />
                                    </x-action-form>
                                    <x-action-form :action="route('inventory.conflicts.dismiss', $conflict)"
                                                   :confirm="__('inventory.conflict.confirm.dismiss')"
                                                   :confirm-label="__('inventory.conflict.action.dismiss')" confirm-icon="do_not_disturb_on">
                                        <x-icon-btn icon="do_not_disturb_on" size="xs" type="submit" :title="__('inventory.conflict.action.dismiss')" />
                                    </x-action-form>
                                </div>
                            @elseif (! $isArticle && $canResolveStock && $conflict->isOpen())
                                <div class="flex justify-end gap-1">
                                    <form method="POST" action="{{ route('inventory.conflicts.compensate', $conflict) }}">
                                        @csrf
                                        <x-icon-btn icon="undo" size="xs" tone="warning" type="submit" :title="__('inventory.conflict.action.compensate')" />
                                    </form>
                                    <form method="POST" action="{{ route('inventory.conflicts.keep-local', $conflict) }}">
                                        @csrf
                                        <x-icon-btn icon="check" size="xs" type="submit" :title="__('inventory.conflict.action.keep_local')" />
                                    </form>
                                </div>
                            @else
                                <span class="text-xs opacity-40">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
        </x-table>

        <x-pagination :paginator="$conflicts" standing />
    @endif
</x-index-page>
@endsection
