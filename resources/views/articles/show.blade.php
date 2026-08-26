{{--
  Created on   : Fri Jun 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $article->name . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('article.title'))

@php
    /** @var \App\Models\Article $article */
    $canManage = auth()->user()?->can('update', $article) ?? false;
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$article->name">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="uppercase text-muted">{{ $article->type->label() }}</span>
                <span class="font-mono text-base-content/70">{{ $article->number ?? __('article.sku_auto_hint') }}</span>
                <span class="badge badge-sm {{ $article->status->value === 'active' ? 'badge-success' : ($article->status->value === 'retired' ? 'badge-ghost' : 'badge-warning') }}">
                    {{ $article->status->label() }}
                </span>
                <span class="text-base-content/70">{{ __('article.field.base_unit') }}: <strong>{{ $article->base_unit }}</strong></span>
                @foreach ($tags ?? collect() as $tag)
                    <x-tag-badge :tag="$tag" />
                @endforeach
            </div>
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('articles.edit', $article)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @if ($article->status->value !== 'retired')
                        <x-action-form :action="route('articles.retire', $article)" :confirm="__('article.confirm.retire')">
                            <x-icon-btn icon="archive" size="sm" type="submit" tone="warning" show-label>{{ __('article.action.retire') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    <x-action-form :action="route('articles.destroy', $article)" method="DELETE" :confirm="__('article.confirm.delete')">
                        <x-icon-btn icon="delete" size="sm" type="submit" tone="error" :title="__('Löschen')" />
                    </x-action-form>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-identifier-issues :issues="$identifierIssues ?? []" />

    @include('articles._tabs', ['article' => $article])

    {{-- Optionen & Werte --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('article.options') }}</h2>
        @forelse ($article->optionDefinitions as $option)
            <div class="border border-base-300 rounded-box p-3 mb-3">
                <div class="font-medium">{{ $option->name }} <span class="opacity-50 font-mono text-xs">{{ $option->code }}</span></div>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach ($option->values as $value)
                        <span class="badge {{ $value->active ? 'badge-outline' : 'badge-ghost line-through' }}">{{ $value->label }} <span class="opacity-50 font-mono ml-1">{{ $value->code }}</span></span>
                    @endforeach
                </div>
                @if ($canManage)
                    <form method="POST" action="{{ route('articles.options.values.store', [$article, $option]) }}" class="flex flex-wrap items-end gap-2 mt-3">
                        @csrf
                        <input name="code" required maxlength="40" placeholder="{{ __('article.field.code') }}" class="input input-sm input-bordered">
                        <input name="label" required maxlength="255" placeholder="{{ __('article.field.label') }}" class="input input-sm input-bordered">
                        <button type="submit" class="btn btn-sm">{{ __('article.action.add_value') }}</button>
                    </form>
                @endif
            </div>
        @empty
            <x-empty-state :title="__('article.no_options')" />
        @endforelse

        @if ($canManage)
            <form method="POST" action="{{ route('articles.options.store', $article) }}" class="flex flex-wrap items-end gap-2 mt-2">
                @csrf
                <input name="code" required maxlength="40" placeholder="{{ __('article.field.code') }}" class="input input-sm input-bordered">
                <input name="name" required maxlength="255" placeholder="{{ __('article.field.option_name') }}" class="input input-sm input-bordered">
                <x-button type="submit" tone="primary" size="sm">{{ __('article.action.add_option') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Varianten --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('article.variants') }}</h2>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('article.field.sku') }}</th>
                    <th>{{ __('article.field.combination') }}</th>
                    <th>{{ __('article.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($article->variants as $variant)
                <tr>
                    <td class="font-mono text-sm">{{ $variant->sku ?? '—' }}</td>
                    <td>{{ $variant->optionValues->pluck('label')->implode(', ') ?: '—' }}</td>
                    <td><span class="badge badge-sm {{ $variant->status->value === 'active' ? 'badge-success' : 'badge-ghost' }}">{{ $variant->status->label() }}</span></td>
                    <td class="text-right">
                        @if ($canManage && $variant->status->value !== 'retired')
                            <form method="POST" action="{{ route('articles.variants.retire', [$article, $variant]) }}">
                                @csrf
                                <x-icon-btn icon="archive" size="xs" type="submit" :title="__('article.action.retire')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="inventory_2"
                               :title="__('article.no_variants')" />
            @endforelse
        </x-table>

        @if ($canManage && $article->optionDefinitions->isNotEmpty())
            <form method="POST" action="{{ route('articles.variants.store', $article) }}" class="flex flex-wrap items-end gap-3 mt-4 border-t border-base-300 pt-3">
                @csrf
                @foreach ($article->optionDefinitions as $option)
                    <div class="fieldset">
                        <label for="variant-option-{{ $option->sqid }}" class="fieldset-label">{{ $option->name }}</label>
                        <select id="variant-option-{{ $option->sqid }}" name="option_value_ids[]" class="select select-sm select-bordered">
                            @foreach ($option->values->where('active', true) as $value)
                                <option value="{{ $value->sqid }}">{{ $value->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="fieldset">
                    <label for="sale_price" class="fieldset-label">{{ __('article.field.sale_price') }}</label>
                    <input id="sale_price" name="sale_price" type="number" step="0.0001" min="0" class="input input-sm input-bordered w-28">
                </div>
                <x-button type="submit" tone="primary" size="sm">{{ __('article.action.add_variant') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Einheiten --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('article.units') }}</h2>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('article.field.code') }}</th>
                    <th>{{ __('article.field.unit_kind') }}</th>
                    <th class="text-right">{{ __('article.field.factor_to_base') }}</th>
                </tr>
            </x-slot:head>
            <tr><td class="font-mono">{{ $article->base_unit }}</td><td>{{ __('article.unit_kind.base') }}</td><td class="text-right">1</td></tr>
            @foreach ($article->units as $unit)
                <tr><td class="font-mono">{{ $unit->code }}</td><td>{{ $unit->kind->label() }}</td><td class="text-right tabular-nums">{{ rtrim(rtrim($unit->factor_to_base, '0'), '.') }}</td></tr>
            @endforeach
        </x-table>
        @if ($canManage)
            <form method="POST" action="{{ route('articles.units.store', $article) }}" class="flex flex-wrap items-end gap-2 mt-2">
                @csrf
                <input name="code" required maxlength="20" placeholder="{{ __('article.field.code') }}" class="input input-sm input-bordered w-28">
                <select name="kind" class="select select-sm select-bordered">
                    @foreach ($unitKinds as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                    @endforeach
                </select>
                <input name="factor_to_base" type="number" step="0.00000001" min="0" required placeholder="{{ __('article.field.factor_to_base') }}" class="input input-sm input-bordered w-32">
                <button type="submit" class="btn btn-sm">{{ __('article.action.add_unit') }}</button>
            </form>
        @endif
    </x-card>

    {{-- Feature 109 (MVP-645): Kennwerte aus Baukostenkatalogen. Sie sagen, was
         das Bauteil üblicherweise kostet — der eigene Preis bleibt davon
         unberührt; der Vergleich ist der Zweck, nicht die Übernahme. --}}
    @if ($article->costBenchmarks->isNotEmpty())
        <x-card>
            <h2 class="mb-3 font-semibold">{{ __('Kennwerte aus Baukostenkatalogen') }}</h2>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Katalog') }}</th>
                        <th>{{ __('Kostenelement') }}</th>
                        <th>{{ __('Einheit') }}</th>
                        <th class="text-right">{{ __('von') }}</th>
                        <th class="text-right">{{ __('Mittel') }}</th>
                        <th class="text-right">{{ __('bis') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($article->costBenchmarks as $benchmark)
                    <tr>
                        <td class="text-base-content/70">{{ $benchmark->catalog?->name ?? '—' }}</td>
                        <td>{{ $benchmark->code ? $benchmark->code . ' ' : '' }}{{ $benchmark->label }}</td>
                        <td class="text-base-content/70">{{ $benchmark->unit ?? '—' }}</td>
                        @foreach (['unit_price_from', 'unit_price_avg', 'unit_price_to'] as $field)
                            <td class="text-right tabular-nums @if ($field === 'unit_price_avg') font-medium @else text-base-content/70 @endif">
                                {{ $benchmark->{$field} !== null
                                    ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $benchmark->{$field}, 2, withThousandsSeparator: true) . ' €'
                                    : '—' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </x-table>
            <p class="mt-2 text-xs text-muted">
                {{ __('Kennwerte stammen aus fremden Katalogen und werden nicht in den Artikelpreis übernommen.') }}
            </p>
        </x-card>
    @endif

    {{-- MVP-605: Verkaufs-Staffelpreise (Quelle der Z-Sätze im DATANORM-Export) --}}
    <x-card>
        <h2 class="font-semibold mb-3">{{ __('article.tiers.title') }}</h2>
        <p class="mb-3 text-sm opacity-70">{{ __('article.tiers.hint') }}</p>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th class="text-right">{{ __('article.tiers.min_qty') }}</th>
                    <th class="text-right">{{ __('article.tiers.unit_price') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($article->priceTiers as $tier)
                <tr>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim($tier->min_qty, '0'), '.') }}</td>
                    <td class="text-right tabular-nums">{{ number_format((float) $tier->unit_price, 4, ',', '.') }} {{ $article->currency?->value ?? 'EUR' }}</td>
                    <td class="text-right">
                        @if ($canManage)
                            <form method="POST" action="{{ route('articles.tiers.destroy', [$article, $tier]) }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Löschen')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-sm opacity-60">{{ __('article.tiers.empty') }}</td></tr>
            @endforelse
        </x-table>
        @if ($canManage)
            <form method="POST" action="{{ route('articles.tiers.store', $article) }}" class="flex flex-wrap items-end gap-2 mt-2">
                @csrf
                <input name="min_qty" type="number" step="0.01" min="0.01" required placeholder="{{ __('article.tiers.min_qty') }}" class="input input-sm input-bordered w-32">
                <input name="unit_price" type="number" step="0.0001" min="0" required placeholder="{{ __('article.tiers.unit_price') }}" class="input input-sm input-bordered w-32">
                <button type="submit" class="btn btn-sm">{{ __('article.tiers.action.add') }}</button>
            </form>
        @endif
    </x-card>

    @if ($article->externalMappings->isNotEmpty())
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('article.external_mappings') }}</h2>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Plugin') }}</th>
                        <th>{{ __('article.field.external_id') }}</th>
                        <th>{{ __('article.field.sync_status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($article->externalMappings as $map)
                    <tr><td>{{ $map->plugin_id }}</td><td class="font-mono">{{ $map->external_id }}</td><td>{{ $map->sync_status }}</td></tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @if ($supplies->isNotEmpty())
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('article.supplies.title') }}</h2>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('article.supplies.supplier') }}</th>
                        <th>{{ __('article.supplies.sku') }}</th>
                        <th class="text-right">{{ __('article.supplies.price') }}</th>
                        <th class="text-right">{{ __('article.supplies.lead_time') }}</th>
                        <th class="text-right">{{ __('article.supplies.moq') }}</th>
                        <th></th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($supplies as $supply)
                    <tr @class(['bg-success/10' => $supply->id === $recommendedSupplyId])>
                        <td>{{ $supply->supplier?->name }}</td>
                        <td class="font-mono text-xs">{{ $supply->supplier_sku }}</td>
                        <td class="text-right tabular-nums">{{ $supply->purchase_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($supply->purchase_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) . ' ' . $supply->currency->value : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $supply->lead_time_days }} {{ __('article.supplies.days') }}</td>
                        <td class="text-right tabular-nums">{{ rtrim(rtrim((string) $supply->moq, '0'), '.') }}</td>
                        <td class="space-x-1">
                            @if ($supply->is_preferred)<x-status-badge tone="success">{{ __('article.supplies.preferred') }}</x-status-badge>@endif
                            @if ($supply->id === $recommendedSupplyId)<x-status-badge tone="info">{{ __('article.supplies.recommended') }}</x-status-badge>@endif
                        </td>
                        <td class="text-right">
                            @can('update', $article)
                                @unless ($supply->is_preferred)
                                    <form method="POST" action="{{ route('articles.supplies.prefer', [$article, $supply]) }}">@csrf
                                        <x-icon-btn icon="star" size="xs" type="submit" :title="__('article.supplies.set_preferred')" />
                                    </form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
