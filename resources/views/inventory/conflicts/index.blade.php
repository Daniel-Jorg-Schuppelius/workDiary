@extends('layouts.app')
@section('title', __('inventory.conflict.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.conflict.title'))

@section('content')
<x-index-page :subtitle="__('inventory.conflict.title')">
    <x-slot:actions>
        <x-icon-btn icon="inventory" size="sm" :href="route('inventory.stock')" show-label>{{ __('inventory.stock') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Tab-Strip über die gemeinsame Komponente (D5; Vollaudit 2026-07, N44). --}}
    <x-tab-nav class="w-fit mb-3" :items="collect(['open', 'all'])->map(fn($tab) => [
        'label' => __('inventory.conflict.filter.' . $tab),
        'route' => 'inventory.conflicts.index',
        'params' => ['status' => $tab],
        'active' => ($filters['status'] ?? 'open') === $tab,
    ])->all()" />

    @if ($conflicts->isEmpty())
        <x-empty-state framed :title="__('inventory.conflict.empty')" />
    @else
        <x-card>
            <x-table>
                <x-slot:head>
                    <th>{{ __('inventory.conflict.col.id') }}</th>
                    <th>{{ __('inventory.conflict.col.operation') }}</th>
                    <th class="text-right">{{ __('inventory.conflict.col.qty') }}</th>
                    <th>{{ __('inventory.conflict.col.status') }}</th>
                    <th class="text-right">{{ __('inventory.conflict.col.actions') }}</th>
                </x-slot:head>

                @foreach ($conflicts as $conflict)
                    @php($snap = $conflict->local_snapshot ?? [])
                    <tr>
                        <td>
                            <div class="font-mono text-xs">#{{ $conflict->referenceable_id }}</div>
                            <div class="text-xs opacity-60">{{ $conflict->plugin_id }}</div>
                        </td>
                        <td>
                            <div>{{ $snap['movement_type'] ?? $snap['operation'] ?? '—' }}</div>
                            <div class="text-xs opacity-60">{{ $snap['stock_state'] ?? '' }}</div>
                        </td>
                        <td class="text-right font-mono">{{ $snap['qty_base'] ?? '—' }}</td>
                        <td>
                            @php($tone = match ($conflict->status) {
                                \App\Models\PendingExternalConflict::STATUS_OPEN => 'warning',
                                \App\Models\PendingExternalConflict::STATUS_COMPENSATED => 'info',
                                default => 'success',
                            })
                            <x-status-badge :tone="$tone">{{ __('inventory.conflict.status.' . $conflict->status) }}</x-status-badge>
                        </td>
                        <td class="text-right">
                            @if ($canResolve && $conflict->isOpen())
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
        </x-card>
    @endif
</x-index-page>
@endsection
