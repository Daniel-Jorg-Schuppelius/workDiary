@extends('layouts.app')
@section('title', __('inventory.count_ui.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.count_ui.title'))

@php /** @var \App\Models\StockCount $count */ @endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('inventory.count_ui.title') . ' — ' . $count->warehouse?->name"
                        :badge="$count->status->label()" badgeTone="ghost"
                        :subtitle="__('inventory.count_ui.counted_at') . ': ' . $count->counted_at?->format('d.m.Y H:i')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('inventory.counts.index', ['warehouse' => $count->warehouse?->sqid])" show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Scan-gestützte Erfassung (E6) --}}
    @if ($canCount && $count->status->isOpen())
        <x-card>
            <form method="POST" action="{{ route('inventory.counts.scan', $count) }}" class="flex items-end gap-2">
                @csrf
                <div class="fieldset grow"><label class="fieldset-label">{{ __('inventory.scan.code') }}</label>
                    <input name="code" autofocus autocomplete="off" class="input input-sm input-bordered w-full font-mono" placeholder="GTIN / SKU / SN / LOT"></div>
                <div class="fieldset"><label class="fieldset-label">{{ __('inventory.count_ui.counted') }}</label>
                    <input name="qty" type="number" step="0.0001" min="0" value="1" class="input input-sm input-bordered w-24"></div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.scan.title') }}</button>
            </form>
        </x-card>
    @endif

    <form method="POST" action="{{ route('inventory.counts.record', $count) }}">
        @csrf
        <x-card padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('inventory.field.variant') }}</th>
                        <th>{{ __('inventory.field.ownership') }}</th>
                        <th class="text-right">{{ __('inventory.count_ui.book') }}</th>
                        <th class="text-right">{{ __('inventory.count_ui.counted') }}</th>
                        <th class="text-right">{{ __('inventory.count_ui.difference') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($count->lines as $line)
                    <tr>
                        <td>{{ $line->variant?->article?->name }} — {{ $line->variant?->name ?? $line->variant?->option_signature }}</td>
                        <td>{{ $line->ownership_type->label() }}</td>
                        <td class="text-right tabular-nums">{{ $line->book_qty }}</td>
                        <td class="text-right">
                            @if ($canCount && $count->status->isOpen())
                                <input type="number" step="0.0001" min="0" name="counted[{{ $line->id }}]"
                                       value="{{ $line->counted_qty }}" class="input input-xs input-bordered w-24 text-right">
                            @else
                                <span class="tabular-nums">{{ $line->counted_qty ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums {{ $line->difference() !== null && $line->difference() !== '0.0000' ? 'text-warning font-medium' : '' }}">
                            {{ $line->difference() ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5"
                                   icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
                                   :title="__('inventory.count_ui.no_counts')" compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($count->status->isOpen())
            <div class="flex flex-wrap gap-2">
                @if ($canCount)
                    <button type="submit" class="btn btn-sm">{{ __('inventory.count_ui.save') }}</button>
                @endif
            </div>
        @endif
    </form>

    @if ($canApply && $count->status->isOpen())
        <x-card>
            <form method="POST" action="{{ route('inventory.counts.apply', $count) }}"
                  onsubmit="return confirm('{{ __('inventory.count_ui.apply') }}?')">
                @csrf
                <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('inventory.count_ui.apply') }}</x-icon-btn>
            </form>
        </x-card>
    @endif
</x-page-shell>
@endsection
