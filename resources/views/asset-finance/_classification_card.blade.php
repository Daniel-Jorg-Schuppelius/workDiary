{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _classification_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- IFRS-16-/HGB-Einschätzung (MVP-947). Erwartet: $contract --}}
@php($snapshot = $contract->classification_snapshot)
<x-card :title="__('asset_finance.classification.title')">
    <p class="text-xs text-muted">{{ __('asset_finance.classification.disclaimer') }}</p>
    @if ($snapshot)
        <x-detail-grid class="mt-2 grid-cols-2">
            <x-detail-grid.row :label="__('asset_finance.classification.ifrs16')">{{ __('asset_finance.classification.ifrs16_result.' . $snapshot['ifrs16']) }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('asset_finance.classification.hgb')">{{ __('asset_finance.classification.hgb_result.' . $snapshot['hgb']) }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('asset_finance.classification.ratio')">{{ $snapshot['ratio'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($snapshot['ratio'] * 100, 0) . ' %' : '—' }} ({{ $snapshot['term_months'] ?? '—' }} / {{ $contract->useful_life_months ?? '—' }} {{ __('asset_finance.classification.months') }})</x-detail-grid.row>
            <x-detail-grid.row :label="__('asset_finance.classification.reasons')">{{ collect($snapshot['reasons'])->map(fn ($r) => __('asset_finance.classification.reason.' . $r))->implode('; ') }}</x-detail-grid.row>
        </x-detail-grid>
        <p class="mt-1 text-xs text-muted">{{ __('asset_finance.classification.assessed_at', ['date' => \Illuminate\Support\Carbon::parse($snapshot['assessed_at'])->format('d.m.Y H:i')]) }}</p>
    @endif
    <details class="mt-2" @if (! $snapshot) open @endif>
        <summary class="cursor-pointer text-sm font-medium">{{ __('asset_finance.classification.assess') }}</summary>
        <form method="POST" action="{{ route('asset-finance.classification.update', $contract) }}" class="mt-2 flex flex-wrap items-end gap-2">
            @csrf
            @method('PUT')
            <x-input-field name="useful_life_months" type="number" min="1" max="1200" :label="__('asset_finance.classification.field.useful_life_months')" :value="old('useful_life_months', $contract->useful_life_months)" />
            <x-input-field name="asset_value_amount" type="number" step="0.01" min="0" :label="__('asset_finance.classification.field.asset_value_amount')" :value="old('asset_value_amount', $contract->asset_value_amount)" />
            <x-checkbox-field name="is_special_lease" :label="__('asset_finance.classification.field.is_special_lease')" :checked="(bool) $contract->is_special_lease" />
            <x-button type="submit" tone="plain">{{ __('asset_finance.classification.assess') }}</x-button>
        </form>
    </details>
</x-card>
