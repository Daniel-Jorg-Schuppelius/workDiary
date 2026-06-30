@extends('layouts.app')
@section('title', $bill->name . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $bill->name)

@section('content')
<x-index-page :subtitle="$bill->project?->name ?: __('gaeb.title')">
    <x-slot:actions>
        <x-icon-btn icon="download" size="sm" :href="route('bill-of-quantities.export', $bill)" show-label>{{ __('gaeb.export.button') }}</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('bill-of-quantities.index')" show-label>{{ __('gaeb.show.back') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Nachkalkulation (MVP-083) --}}
    <x-card>
        <div class="flex flex-wrap items-center gap-6">
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.costing.planned') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($costing['planned'], 2, ',', '.') }} {{ $costing['currency'] }}</div>
            </div>
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.costing.executed') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($costing['executed'], 2, ',', '.') }} {{ $costing['currency'] }}</div>
            </div>
            <div>
                <div class="text-xs uppercase opacity-60">{{ __('gaeb.costing.remaining') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($costing['remaining'], 2, ',', '.') }} {{ $costing['currency'] }}</div>
            </div>
            <div class="flex-1 min-w-48">
                <div class="text-xs uppercase opacity-60 mb-1">{{ __('gaeb.costing.progress') }} ({{ round($costing['progress'] * 100) }}%)</div>
                <progress class="progress progress-primary w-full" value="{{ round($costing['progress'] * 100) }}" max="100"></progress>
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('bill-of-quantities.transition', $bill) }}" class="flex items-end gap-2">@csrf
                    <div>
                        <label class="label py-0"><span class="label-text text-xs">{{ __('gaeb.columns.status') }}</span></label>
                        <select name="status" class="select select-bordered select-sm">
                            @foreach ($billStatuses as $st)
                                <option value="{{ $st->value }}" @selected($bill->status === $st)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm">{{ __('gaeb.workflow.status') }}</button>
                </form>
            @endif
        </div>
    </x-card>

    {{-- Positionen (MVP-082/083/084) --}}
    <x-card padding="p-0" class="mt-4">
        <x-table>
            <x-slot:head>
                <th>{{ __('gaeb.columns.reference_no') }}</th>
                <th>{{ __('gaeb.columns.short_text') }}</th>
                <th>{{ __('gaeb.columns.type') }}</th>
                <th class="text-right">{{ __('gaeb.columns.quantity') }}</th>
                <th class="text-right">{{ __('gaeb.columns.executed') }}</th>
                <th class="text-right">{{ __('gaeb.columns.remaining') }}</th>
                <th>{{ __('gaeb.columns.unit') }}</th>
                <th class="text-right">{{ __('gaeb.columns.unit_price') }}</th>
                <th>{{ __('gaeb.columns.status') }}</th>
                @if ($canManage)<th class="text-right">{{ __('gaeb.progress.record') }}</th>@endif
            </x-slot:head>
            @foreach ($bill->items as $item)
                <tr>
                    <td class="font-mono text-sm whitespace-nowrap">{{ $item->reference_no }}</td>
                    <td>
                        {{ $item->short_text ?: '—' }}
                        @if ($item->is_addendum)<span class="badge badge-xs badge-warning ml-1">N</span>@endif
                        @foreach ($item->mappings as $map)
                            <span class="badge badge-xs badge-ghost ml-1">{{ \App\Support\EntityType::label($map->mappable_type) }}</span>
                        @endforeach
                    </td>
                    <td><span class="badge badge-sm badge-ghost">{{ $item->type->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $item->quantity !== null ? rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') : '—' }}</td>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format($item->executedQuantity(), 3, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format($item->remainingQuantity(), 3, ',', '.'), '0'), ',') }}</td>
                    <td>{{ $item->unit ?: '—' }}</td>
                    <td class="text-right tabular-nums">{{ $item->unit_price !== null ? number_format((float) $item->unit_price, 2, ',', '.') : '—' }}</td>
                    <td><span class="badge badge-sm badge-ghost">{{ $item->status->label() }}</span></td>
                    @if ($canManage)
                        <td>
                            <form method="POST" action="{{ route('bill-of-quantities.items.progress', $item) }}" class="flex items-center justify-end gap-1">@csrf
                                <input type="number" step="0.001" name="quantity" class="input input-bordered input-xs w-24" placeholder="0" required>
                                <button type="submit" class="btn btn-xs btn-primary">+</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-table>
    </x-card>

    {{-- Nachtrag anlegen (MVP-084) --}}
    @if ($canManage)
        <x-card class="mt-4">
            <h2 class="text-sm font-semibold mb-2">{{ __('gaeb.workflow.add_addendum') }}</h2>
            <form method="POST" action="{{ route('bill-of-quantities.addenda.add', $bill) }}" class="flex flex-wrap items-end gap-2">@csrf
                <input type="text" name="reference_no" class="input input-bordered input-sm w-28" placeholder="{{ __('gaeb.columns.reference_no') }}" required>
                <input type="text" name="short_text" class="input input-bordered input-sm flex-1 min-w-48" placeholder="{{ __('gaeb.columns.short_text') }}">
                <input type="number" step="0.001" name="quantity" class="input input-bordered input-sm w-24" placeholder="{{ __('gaeb.columns.quantity') }}">
                <input type="text" name="unit" class="input input-bordered input-sm w-20" placeholder="{{ __('gaeb.columns.unit') }}">
                <input type="number" step="0.01" name="unit_price" class="input input-bordered input-sm w-24" placeholder="{{ __('gaeb.columns.unit_price') }}">
                <button type="submit" class="btn btn-sm">{{ __('gaeb.workflow.add_addendum') }}</button>
            </form>
        </x-card>
    @endif

    {{-- Restleistung (MVP-084) --}}
    <h2 class="text-sm font-semibold mt-6 mb-2">{{ __('gaeb.workflow.remaining_title') }}</h2>
    @if ($remaining->isEmpty())
        <p class="text-sm opacity-60">{{ __('gaeb.workflow.no_remaining') }}</p>
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('gaeb.columns.reference_no') }}</th>
                    <th>{{ __('gaeb.columns.short_text') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.remaining') }}</th>
                    <th>{{ __('gaeb.columns.unit') }}</th>
                </x-slot:head>
                @foreach ($remaining as $item)
                    <tr>
                        <td class="font-mono text-sm whitespace-nowrap">{{ $item->reference_no }}</td>
                        <td>{{ $item->short_text ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format($item->remainingQuantity(), 3, ',', '.'), '0'), ',') }}</td>
                        <td>{{ $item->unit ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    {{-- Importhistorie (MVP-081) --}}
    <h2 class="text-sm font-semibold mt-6 mb-2">{{ __('gaeb.show.history') }}</h2>
    @if ($imports->isEmpty())
        <p class="text-sm opacity-60">{{ __('gaeb.show.no_imports') }}</p>
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('gaeb.show.imported_at') }}</th>
                    <th>{{ __('gaeb.columns.phase') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.items') }}</th>
                    <th>{{ __('gaeb.columns.status') }}</th>
                </x-slot:head>
                @foreach ($imports as $import)
                    <tr>
                        <td class="text-sm">{{ $import->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $import->phase?->label() ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ $import->item_count }}</td>
                        <td><span class="badge badge-sm badge-ghost">{{ $import->status->label() }}</span></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-index-page>
@endsection
