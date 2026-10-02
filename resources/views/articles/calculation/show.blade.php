{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vorkalkulation einer Leistung (MVP-1055). Erwartet: $article, $result (ServiceCalculation|null), $canEdit --}}
@extends('layouts.app')
@section('title', $article->name . ' — ' . __('article.calculation.title'))
@section('nav-title', __('article.title'))

@php
    $eur = static fn (?\CommonToolkit\ValueObjects\Money $m, int $d = 2): string => $m === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($m->toFloat(), $d, withThousandsSeparator: true) . ' €';
    $num = static fn ($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 4, trimTrailingZeros: true);
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$article->name" :subtitle="__('article.calculation.subtitle')">
            <x-slot:actions>
                @if ($canEdit && $result !== null)
                    <x-action-form :action="route('articles.calculation.adopt-price', $article)"
                                   :confirm="__('article.calculation.adopt_confirm', ['price' => $eur($result->price)])"
                                   :confirm-label="__('article.calculation.adopt_price')">
                        <x-icon-btn icon="sell" tone="primary" size="sm" type="submit" show-label>{{ __('article.calculation.adopt_price') }}</x-icon-btn>
                    </x-action-form>
                @endif
                <x-icon-btn icon="tune" tone="outline" size="sm" :href="route('articles.calculation-scheme.edit')" show-label>{{ __('article.calculation.scheme_title') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('articles._tabs', ['article' => $article])

    <div class="grid gap-3 sm:grid-cols-4">
        <x-kpi-tile :label="__('article.calculation.kpi.cost')" :value="$eur($result?->cost)" />
        <x-kpi-tile :label="__('article.calculation.kpi.price')" :value="$eur($result?->price)" tone="primary" />
        <x-kpi-tile :label="__('article.calculation.kpi.current_price')" :value="$eur($article->default_sale_price)"
                    :tone="$result !== null && $article->default_sale_price !== null && ! $article->default_sale_price->withScale(2)->equals($result->price) ? 'warning' : 'neutral'" />
        <x-kpi-tile :label="__('article.calculation.kpi.labour_share')"
                    :value="$result?->labourShare !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $result->labourShare->getNumericValue(), 2) . ' %' : '—'"
                    :hint="$result !== null ? __('article.calculation.minutes', ['minutes' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($result->labourMinutes, 0)]) : null" />
    </div>

    <x-card :title="__('article.calculation.approaches')" padding="p-0">
        @if ($canEdit)
            <x-slot:actions>
                <x-action-menu icon="add" tone="primary" :label="__('article.calculation.add_approach')">
                    @foreach (\App\Enums\Article\CostKind::cases() as $kind)
                        <x-icon-btn icon="add" size="sm" data-entry-modal-trigger
                                    :href="route('articles.cost-approaches.create', [$article, 'kind' => $kind->value])"
                                    show-label>{{ $kind->label() }}</x-icon-btn>
                    @endforeach
                </x-action-menu>
            </x-slot:actions>
        @endif
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('article.calculation.col.kind') }}</th>
                    <th>{{ __('article.calculation.col.description') }}</th>
                    <th class="text-right">{{ __('article.calculation.col.quantity') }}</th>
                    <th class="text-right">{{ __('article.calculation.col.basis') }}</th>
                    @if ($canEdit)<th class="text-right">{{ __('Aktionen') }}</th>@endif
                </tr>
            </x-slot:head>
            @forelse ($article->costApproaches as $approach)
                <tr>
                    <td>{{ $approach->cost_kind->label() }}</td>
                    <td>{{ $approach->description ?: ($approach->componentArticle?->name ?? $approach->wageGroup?->name ?? '—') }}</td>
                    <td class="text-right tabular-nums">{{ $num($approach->quantity) }} {{ $approach->unit }}</td>
                    <td class="text-right tabular-nums">
                        @if ($approach->cost_kind === \App\Enums\Article\CostKind::Labour)
                            {{ __('article.calculation.minutes', ['minutes' => $num($approach->minutes ?? 0)]) }}@if ($approach->wageGroup) · {{ $approach->wageGroup->name }}@endif
                        @elseif ($approach->componentArticle?->default_purchase_price !== null)
                            {{ $eur($approach->componentArticle->default_purchase_price, 4) }}
                        @else
                            {{ $eur($approach->unit_cost_amount, 4) }}
                        @endif
                    </td>
                    @if ($canEdit)
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('articles.cost-approaches.edit', [$article, $approach])" :title="__('Bearbeiten')" />
                            <x-action-form :action="route('articles.cost-approaches.destroy', [$article, $approach])" method="DELETE"
                                           :confirm="__('article.calculation.delete_confirm')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('Entfernen')">
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                            </x-action-form>
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty icon="calculate" :colspan="$canEdit ? 5 : 4" :title="__('article.calculation.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    @if ($result !== null)
        <x-card :title="__('article.calculation.result')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('article.calculation.col.kind') }}</th>
                        <th class="text-right">{{ __('article.calculation.col.cost') }}</th>
                        <th class="text-right">{{ __('article.calculation.col.markup') }}</th>
                        <th class="text-right">{{ __('article.calculation.col.price') }}</th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-semibold">
                        <td>{{ __('article.calculation.total') }}</td>
                        <td class="text-right tabular-nums">{{ $eur($result->cost, 4) }}</td>
                        <td></td>
                        <td class="text-right tabular-nums">{{ $eur($result->price) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($result->kinds as $key => $row)
                    <tr>
                        <td>{{ \App\Enums\Article\CostKind::from($key)->label() }}</td>
                        <td class="text-right tabular-nums">{{ $eur($row['cost'], 4) }}</td>
                        <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['markup']->getNumericValue(), 2) }} %</td>
                        <td class="text-right tabular-nums">{{ $eur($row['price']) }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
    <p class="text-xs text-muted">{{ __('article.calculation.note') }}</p>
</x-page-shell>
@endsection
