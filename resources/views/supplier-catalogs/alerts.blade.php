@extends('layouts.app')
@section('title', __('procurement.alert.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.alert.title'))

@section('content')
<x-index-page :subtitle="__('procurement.alert.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('supplier-catalogs.index')" show-label>{{ __('procurement.catalog.title') }}</x-icon-btn>
    </x-slot:actions>

    @if ($alerts->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">check_circle</span>'
                       :title="__('procurement.alert.empty')" />
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('procurement.alert.col.type') }}</th>
                    <th>{{ __('procurement.catalog.col.internal_article') }}</th>
                    <th>{{ __('procurement.field.supplier') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.old_ek') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.new_ek') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.sale') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.margin') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.actions') }}</th>
                </x-slot:head>
                @foreach ($alerts as $alert)
                    @php
                        $impacts = $alert->impacts ?? [];
                        $impactGroups = [
                            'purchase_orders' => $impacts['purchase_orders'] ?? [],
                            'boq_items' => $impacts['boq_items'] ?? [],
                            'manufacturing_orders' => $impacts['manufacturing_orders'] ?? [],
                        ];
                        $hasImpacts = array_filter($impactGroups) !== [];
                    @endphp
                    <tr>
                        <td>
                            <span class="badge badge-sm {{ $alert->type === \App\Models\PricingChangeAlert::TYPE_AVAILABILITY ? 'badge-warning' : 'badge-error badge-outline' }}">
                                {{ __('procurement.alert.type.' . $alert->type) }}
                            </span>
                        </td>
                        <td>
                            {{ $alert->article?->name ?: '—' }}
                            @if ($hasImpacts)
                                <div class="text-xs opacity-70 mt-0.5">
                                    @foreach ($impactGroups as $group => $labels)
                                        @if ($labels !== [])
                                            <div>{{ __('procurement.alert.impacts.' . $group) }}: {{ implode(', ', $labels) }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="text-sm">{{ $alert->supplier?->name ?: '—' }}</td>
                        <td class="text-right tabular-nums text-sm opacity-70">
                            {{ $alert->old_purchase_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $alert->old_purchase_price, 2, withThousandsSeparator: true) : '—' }}
                        </td>
                        <td class="text-right tabular-nums font-medium">
                            {{ $alert->new_purchase_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $alert->new_purchase_price, 2, withThousandsSeparator: true) : '—' }}
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $alert->sale_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $alert->sale_price, 2, withThousandsSeparator: true) : '—' }}
                        </td>
                        <td class="text-right tabular-nums">
                            @if ($alert->type === \App\Models\PricingChangeAlert::TYPE_AVAILABILITY)
                                <span class="text-warning text-sm">
                                    {{ $impacts['availability']['old'] ?? '—' }} → {{ $impacts['availability']['new'] ?? '—' }}
                                </span>
                            @elseif ($alert->new_margin !== null)
                                <span class="text-error font-medium">{{ rtrim(rtrim($alert->new_margin, '0'), '.') }} %</span>
                                @if ($alert->min_margin !== null)
                                    <span class="opacity-50 text-xs">(min {{ rtrim(rtrim($alert->min_margin, '0'), '.') }} %)</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('supplier-catalogs.alerts.acknowledge', $alert) }}">@csrf
                                <x-icon-btn icon="done" size="xs" tone="success" type="submit" :title="__('procurement.alert.action.acknowledge')" />
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$alerts" standing />
    @endif
</x-index-page>
@endsection
</content>
