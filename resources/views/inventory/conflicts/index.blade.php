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
        'params' => ['status' => $tab],
        'active' => ($filters['status'] ?? 'open') === $tab,
    ])->all()" />

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
                    <tr class="hover">
                        <td>
                            <div>{{ __('inventory.conflict.type.' . $conflict->conflict_type) }}</div>
                            <div class="text-xs opacity-60">{{ $pluginLabels[$conflict->plugin_id] ?? $conflict->plugin_id }}</div>
                        </td>
                        <td>
                            <div class="font-mono text-xs">#{{ $conflict->referenceable_id }}</div>
                        </td>
                        <td>
                            <div>{{ $snap['movement_type'] ?? $snap['operation'] ?? '—' }}</div>
                            <div class="text-xs opacity-60">{{ $snap['stock_state'] ?? '' }}</div>
                        </td>
                        <td class="text-right font-mono">{{ $snap['qty_base'] ?? '—' }}</td>
                        <td>
                            <x-status-badge :tone="$conflict->status->tone()">{{ $conflict->status->label() }}</x-status-badge>
                        </td>
                        <td class="text-right">
                            @if ($canResolveStock && $conflict->isOpen())
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
