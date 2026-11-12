{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('inventory.count_ui.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.count_ui.title'))

@php /** @var \App\Models\Inventory\StockCount $count */ @endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$count->warehouse?->name"
                        :badge="$count->status->label()" badgeTone="ghost"
                        :subtitle="__('inventory.count_ui.counted_at') . ': ' . $count->counted_at?->fdatetime()"
                        :back="route('inventory.counts.index', ['warehouse' => $count->warehouse?->sqid])" :back-label="__('Zurück')">
            <x-slot:actions>
                @if ($canCount && $count->status->isCounting())
                    <x-icon-btn icon="smartphone" size="sm" :href="route('inventory.counts.mobile', $count)" show-label>{{ __('inventory.count_ui.mobile_open') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Scan-gestützte Erfassung (E6) --}}
    @if ($canCount && $count->status->isCounting())
        <x-card>
            <form method="POST" action="{{ route('inventory.counts.scan', $count) }}" class="flex items-end gap-2">
                @csrf
                <div class="fieldset grow"><label for="code" class="fieldset-label">{{ __('inventory.scan.code') }}</label>
                    <input id="code" name="code" autofocus autocomplete="off" class="input input-sm input-bordered w-full font-mono" placeholder="GTIN / SKU / SN / LOT"></div>
                <div class="fieldset"><label for="qty" class="fieldset-label">{{ __('inventory.count_ui.counted') }}</label>
                    <input id="qty" name="qty" type="number" step="0.0001" min="0" value="1" class="input input-sm input-bordered w-24"></div>
                {{-- Einheit der Zählung (MVP-1584): gespeichert wird in Basiseinheit. --}}
                @php($countUnits = $count->lines->flatMap(fn ($l) => $l->variant?->article?->units ?? [])->pluck('code')->unique()->sort()->values())
                @if ($countUnits->isNotEmpty())
                    <div class="fieldset"><label for="count-scan-unit" class="fieldset-label">{{ __('inventory.field.unit') }}</label>
                        <select id="count-scan-unit" name="unit" class="select select-sm select-bordered">
                            <option value="">{{ __('inventory.field.unit_base') }}</option>
                            @foreach ($countUnits as $unitCode)
                                <option value="{{ $unitCode }}">{{ $unitCode }}</option>
                            @endforeach
                        </select></div>
                @endif
                <x-button type="submit" tone="primary" size="sm">{{ __('inventory.scan.title') }}</x-button>
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
                            @if ($canCount && $count->status->isCounting())
                                <input type="number" step="0.0001" min="0" name="counted[{{ $line->id }}]"
                                       value="{{ $line->counted_qty }}" class="input input-xs input-bordered w-24 text-right">
                                @if (($line->variant?->article?->units ?? collect())->isNotEmpty())
                                    <select name="unit[{{ $line->id }}]" class="select select-xs select-bordered" aria-label="{{ __('inventory.field.unit') }}">
                                        <option value="">{{ $line->variant->article->base_unit }}</option>
                                        @foreach ($line->variant->article->units as $unit)
                                            <option value="{{ $unit->code }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                @endif
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
                                   icon="inventory_2"
                                   :title="__('inventory.count_ui.no_counts')" compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($count->status->isCounting() && $canCount)
            <div class="flex flex-wrap gap-2">
                <x-button type="submit" tone="plain">{{ __('inventory.count_ui.save') }}</x-button>
            </div>
        @endif
    </form>

    {{-- Zählen, Prüfen und Freigeben (Feature 048 §Inventur, MVP-1620) --}}
    @if ($count->status->isOpen() && ($canCount || $canApply))
        <x-card>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($canCount && $count->status->isCounting())
                    <x-action-form :action="route('inventory.counts.transition', $count)" :confirm="__('inventory.count_ui.submit_review_confirm')">
                        <input type="hidden" name="status" value="{{ \App\Enums\Inventory\StockCountStatus::Review->value }}">
                        <x-icon-btn icon="fact_check" size="sm" type="submit" show-label>{{ __('inventory.count_ui.submit_review') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($canApply && $count->status === \App\Enums\Inventory\StockCountStatus::Review)
                    <x-action-form :action="route('inventory.counts.transition', $count)">
                        <input type="hidden" name="status" value="{{ \App\Enums\Inventory\StockCountStatus::Counting->value }}">
                        <x-icon-btn icon="undo" size="sm" type="submit" show-label>{{ __('inventory.count_ui.reopen') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($canApply)
                    <x-action-form :action="route('inventory.counts.transition', $count)" :confirm="__('inventory.count_ui.cancel_confirm')" :confirm-label="__('inventory.count_ui.cancel')">
                        <input type="hidden" name="status" value="{{ \App\Enums\Inventory\StockCountStatus::Cancelled->value }}">
                        <x-icon-btn icon="block" tone="error" size="sm" type="submit" show-label>{{ __('inventory.count_ui.cancel') }}</x-icon-btn>
                    </x-action-form>
                    <x-action-form :action="route('inventory.counts.apply', $count)" :confirm="__('inventory.count_ui.apply').'?'">
                        <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('inventory.count_ui.apply') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </div>
        </x-card>
    @endif
</x-page-shell>
@endsection
