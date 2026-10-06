{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : footprint.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- CO₂-Fußabdruck je Stück (MVP-960). Erwartet: $article, $result --}}
@extends('layouts.app')
@section('title', $article->name . ' — ' . __('article.footprint.title'))
@section('nav-title', __('article.title'))

@php
    $kg = static fn (?string $v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 3, withThousandsSeparator: true, trimTrailingZeros: true) . ' kg';
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$article->name" :subtitle="__('article.footprint.subtitle')" />
    </x-slot:toolbar>

    @include('articles._tabs', ['article' => $article])

    <div class="grid gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('article.footprint.kpi.total')" :value="$kg($result['total_kg'])" :tone="$result['complete'] ? 'neutral' : 'warning'"
                    :hint="$result['complete'] ? null : __('article.footprint.incomplete', ['count' => count($result['missing'])])" />
        <x-kpi-tile :label="__('article.footprint.kpi.material')" :value="$kg($result['material_kg'])" />
        <x-kpi-tile :label="__('article.footprint.kpi.process')" :value="$kg($result['process_kg'])" />
    </div>

    <x-card :title="__('article.footprint.factors')">
        @can('update', $article)
            <form method="POST" action="{{ route('articles.footprint.update', $article) }}" class="grid gap-2 sm:grid-cols-3" data-entry-form>
                @csrf
                @method('PUT')
                <x-input-field name="pcf_factor_kg" type="number" step="0.0001" min="0" :label="__('article.footprint.field.factor')" :value="$article->pcf_factor_kg" :hint="__('article.footprint.hint.factor', ['unit' => $article->base_unit])" />
                <x-input-field name="pcf_process_kg" type="number" step="0.0001" min="0" :label="__('article.footprint.field.process')" :value="$article->pcf_process_kg" />
                <x-input-field name="pcf_source" :label="__('article.footprint.field.source')" :value="$article->pcf_source" />
                <div class="sm:col-span-3 flex justify-end"><x-button type="submit">{{ __('article.footprint.save') }}</x-button></div>
            </form>
        @endcan
    </x-card>

    <x-card :title="__('article.footprint.lines')" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('article.footprint.col.article') }}</th>
                    <th>{{ __('article.footprint.col.kind') }}</th>
                    <th class="text-right">{{ __('article.footprint.col.quantity') }}</th>
                    <th class="text-right">{{ __('article.footprint.col.factor') }}</th>
                    <th class="text-right">{{ __('article.footprint.col.kg') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($result['lines'] as $line)
                <tr>
                    <td><span class="text-muted">{{ str_repeat('· ', max(0, $line['level'] - 1)) }}</span>{{ $line['article']->name }}</td>
                    <td>{{ __('article.footprint.kind.' . $line['source']) }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line['quantity'], 4, trimTrailingZeros: true) }}</td>
                    <td class="text-right tabular-nums">{{ $line['factor'] === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line['factor'], 4, trimTrailingZeros: true) }}</td>
                    <td class="text-right tabular-nums {{ $line['kg'] === null && $line['source'] === 'buy' ? 'text-warning' : '' }}">{{ $kg($line['kg']) }}</td>
                </tr>
            @empty
                <x-table.empty icon="eco" :colspan="5" :title="__('article.footprint.no_bom')" compact />
            @endforelse
        </x-table>
    </x-card>
    <p class="text-xs text-muted">{{ __('article.footprint.note') }}</p>
</x-page-shell>
@endsection
