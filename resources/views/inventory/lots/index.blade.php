{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.lot.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.lot.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.lot.subtitle')">
    @if ($lots->total() === 0)
        <x-empty-state framed :title="__('inventory.lot.empty')" />
    @else
        {{-- Zusammenführen nur zwischen aktiven Chargen: eine gesperrte muss erst freigegeben werden. --}}
        @if ($canManage && $mergeable->count() > 1)
            <x-card>
                <h2 class="font-semibold mb-2">{{ __('inventory.lot.merge') }}</h2>
                <form method="POST" action="{{ route('inventory.lots.merge') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset"><label for="from" class="fieldset-label">{{ __('inventory.lot.from') }}</label>
                        <select id="from" name="from" class="select select-sm select-bordered" required>
                            @foreach ($mergeable as $lot)<option value="{{ $lot->sqid }}">{{ $lot->lot_no }}</option>@endforeach
                        </select></div>
                    <div class="fieldset"><label for="into" class="fieldset-label">{{ __('inventory.lot.into') }}</label>
                        <select id="into" name="into" class="select select-sm select-bordered" required>
                            @foreach ($mergeable as $lot)<option value="{{ $lot->sqid }}">{{ $lot->lot_no }}</option>@endforeach
                        </select></div>
                    <x-button type="submit" tone="plain">{{ __('inventory.lot.merge') }}</x-button>
                </form>
            </x-card>
        @endif

        <x-table :zebra="true" scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('inventory.lot.lot_no') }}</th>
                    <th>{{ __('inventory.lot.article') }}</th>
                    <th>{{ __('inventory.lot.best_before') }}</th>
                    <th class="text-right">{{ __('inventory.lot.on_hand') }}</th>
                    @if ($canManage)
                        <th>{{ __('inventory.lot.split') }}</th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($lots as $lot)
                @php
                    $isActive = $lot->status === \App\Enums\Inventory\StockLotStatus::Active;
                    $isBlocked = $lot->status === \App\Enums\Inventory\StockLotStatus::Blocked;
                @endphp
                <tr>
                    <td class="font-mono">
                        {{ $lot->lot_no }} <x-status-badge :tone="$isBlocked ? 'warning' : 'plain'" size="xs">{{ $lot->status->label() }}</x-status-badge>
                        @if ($isBlocked && $lot->blocked_reason)
                            <span class="block font-sans text-xs text-muted">{{ $lot->blocked_reason }} · {{ $lot->blockedBy?->name ?? '—' }} · {{ $lot->blocked_at?->fdatetime() ?? '—' }}</span>
                        @elseif ($lot->mergedInto)
                            <span class="block font-sans text-xs text-muted">{{ __('inventory.lot.merged_into', ['lot' => $lot->mergedInto->lot_no]) }}</span>
                        @endif
                    </td>
                    <td>{{ $lot->variant?->article?->name }}</td>
                    <td>{{ $lot->best_before?->fdate() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $onHand[$lot->id] }}</td>
                    @if ($canManage)
                        <td>
                            @if ($isActive)
                                <form method="POST" action="{{ route('inventory.lots.split') }}" class="flex items-end gap-1">
                                    @csrf
                                    <input type="hidden" name="lot" value="{{ $lot->sqid }}">
                                    <input aria-label="{{ __('inventory.lot.qty') }}" name="qty" type="number" step="0.0001" min="0.0001" placeholder="{{ __('inventory.lot.qty') }}" class="input input-xs input-bordered w-20">
                                    <input aria-label="{{ __('inventory.lot.new_lot_no') }}" name="new_lot_no" type="text" maxlength="80" placeholder="{{ __('inventory.lot.new_lot_no') }}" class="input input-xs input-bordered w-28">
                                    <x-button type="submit" tone="plain" size="xs">{{ __('inventory.lot.split') }}</x-button>
                                </form>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($isActive)
                                <x-icon-btn icon="lock" size="xs" tone="ghost" data-entry-modal-trigger
                                            :href="route('inventory.lots.block.create', $lot)"
                                            show-label>{{ __('inventory.lot.block.action') }}</x-icon-btn>
                            @elseif ($isBlocked)
                                <x-icon-btn icon="lock_open" size="xs" tone="ghost" data-entry-modal-trigger
                                            :href="route('inventory.lots.unblock.create', $lot)"
                                            show-label>{{ __('inventory.lot.unblock.action') }}</x-icon-btn>
                            @endif
                            <x-icon-btn icon="label" size="xs" tone="ghost"
                                        :href="route('inventory.labels.lot', $lot)"
                                        target="_blank" :title="__('Etikett drucken')" />
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty :colspan="$canManage ? 6 : 4"
                               icon="inventory_2"
                               :title="__('inventory.lot.empty')" compact />
            @endforelse
        </x-table>
        <x-pagination :paginator="$lots" standing />
    @endif
</x-index-page>
@endsection
