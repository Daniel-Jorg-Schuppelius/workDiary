@extends('layouts.app')
@section('title', __('inventory.lot.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.lot.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.lot.subtitle')">
    @if ($lots->total() === 0)
        <x-empty-state framed :title="__('inventory.lot.empty')" />
    @else
        @if ($canManage)
            <x-card>
                <h2 class="font-semibold mb-2">{{ __('inventory.lot.merge') }}</h2>
                <form method="POST" action="{{ route('inventory.lots.merge') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset"><label class="fieldset-label">{{ __('inventory.lot.from') }}</label>
                        <select name="from" class="select select-sm select-bordered" required>
                            @foreach ($lots as $lot)<option value="{{ $lot->sqid }}">{{ $lot->lot_no }}</option>@endforeach
                        </select></div>
                    <div class="fieldset"><label class="fieldset-label">{{ __('inventory.lot.into') }}</label>
                        <select name="into" class="select select-sm select-bordered" required>
                            @foreach ($lots as $lot)<option value="{{ $lot->sqid }}">{{ $lot->lot_no }}</option>@endforeach
                        </select></div>
                    <button type="submit" class="btn btn-sm">{{ __('inventory.lot.merge') }}</button>
                </form>
            </x-card>
        @endif

        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('inventory.lot.lot_no') }}</th>
                        <th>{{ __('inventory.lot.article') }}</th>
                        <th>{{ __('inventory.lot.best_before') }}</th>
                        <th class="text-right">{{ __('inventory.lot.on_hand') }}</th>
                        @if ($canManage)<th>{{ __('inventory.lot.split') }}</th>@endif
                    </tr>
                </x-slot:head>
                @forelse ($lots as $lot)
                    <tr>
                        <td class="font-mono">{{ $lot->lot_no }} <span class="badge badge-xs">{{ $lot->status }}</span></td>
                        <td>{{ $lot->variant?->article?->name }}</td>
                        <td>{{ $lot->best_before?->format('d.m.Y') ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $onHand[$lot->id] }}</td>
                        @if ($canManage)
                            <td>
                                <form method="POST" action="{{ route('inventory.lots.split') }}" class="flex items-end gap-1">
                                    @csrf
                                    <input type="hidden" name="lot" value="{{ $lot->sqid }}">
                                    <input name="qty" type="number" step="0.0001" min="0.0001" placeholder="{{ __('inventory.lot.qty') }}" class="input input-xs input-bordered w-20">
                                    <input name="new_lot_no" type="text" maxlength="80" placeholder="{{ __('inventory.lot.new_lot_no') }}" class="input input-xs input-bordered w-28">
                                    <button type="submit" class="btn btn-xs">{{ __('inventory.lot.split') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <x-table.empty :colspan="$canManage ? 5 : 4"
                                   icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
                                   :title="__('inventory.lot.empty')" compact />
                @endforelse
            </x-table>
        </x-card>
        <x-pagination :paginator="$lots" standing />
    @endif
</x-index-page>
@endsection
