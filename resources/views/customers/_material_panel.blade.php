{{--
  Created on   : Wed Aug 06 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _material_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Materialkosten & Gewinn (Umsatz − zugeordnete Materialkosten). Erwartet:
     $customer, $materialAllocations, $invoicedRange, $materialRange,
     $profitRange, $statsRangeLabel. Nur mit update-Recht eingebunden. --}}

@php
    $cur = $customer->currency->value;
    $fmt = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' ' . $cur;
    $margin = $invoicedRange > 0.0 ? round($profitRange / $invoicedRange * 100, 1) : null;
    $profitTone = $profitRange >= 0.0 ? 'text-success' : 'text-error';
@endphp

<x-card :title="__('customer-material.panel_title')" icon="savings" id="customer-material" padding="p-0">
    <x-slot:actions>
        @if ($inventoryModuleActive ?? false)
            <x-icon-btn icon="inventory_2" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('customers.material-costs.stock.create', $customer)"
                        show-label>{{ __('customer-material.stock_title') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('customers.material-costs.create', $customer)"
                    show-label>{{ __('customer-material.add_title') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-px border-b border-base-300 bg-base-300 sm:grid-cols-3">
        <div class="bg-base-100 px-4 py-3">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('customer-material.revenue') }}</div>
            <div class="font-['Space_Grotesk'] text-lg font-semibold tabular-nums">{{ $fmt($invoicedRange) }}</div>
        </div>
        <div class="bg-base-100 px-4 py-3">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('customer-material.material_cost') }}</div>
            <div class="font-['Space_Grotesk'] text-lg font-semibold tabular-nums">{{ $fmt($materialRange) }}</div>
        </div>
        <div class="bg-base-100 px-4 py-3">
            <div class="text-xs uppercase tracking-wider text-base-content/60">
                {{ __('customer-material.profit') }}
                @if ($margin !== null)
                    <span class="ml-1 font-normal normal-case">({{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($margin, 1) }} % {{ __('customer-material.margin') }})</span>
                @endif
            </div>
            <div class="font-['Space_Grotesk'] text-lg font-semibold tabular-nums {{ $profitTone }}">{{ $fmt($profitRange) }}</div>
        </div>
    </div>

    <p class="px-4 pt-2 text-xs text-base-content/50">{{ __('customer-material.range_hint', ['range' => $statsRangeLabel]) }}</p>
    <p class="px-4 pb-1 text-xs text-base-content/50">{{ __('customer-material.double_count_hint') }}</p>

    @if ($materialAllocations->isEmpty())
        <p class="px-4 py-6 text-sm text-base-content/60">{{ __('customer-material.empty_hint') }}</p>
    @else
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('customer-material.date') }}</th>
                    <th>{{ __('customer-material.description') }}</th>
                    <th>{{ __('customer-material.project') }}</th>
                    <th class="text-right">{{ __('customer-material.amount') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($materialAllocations as $allocation)
                <tr>
                    <td class="tabular-nums text-sm">{{ $allocation->allocated_on?->fdate() ?? '—' }}</td>
                    <td class="text-sm">
                        {{ $allocation->description ?? '—' }}
                        @if ($allocation->source_type === \App\Models\StockMovement::class)
                            <span class="badge badge-ghost badge-xs align-middle">{{ __('customer-material.source_stock') }}</span>
                        @elseif ($allocation->source_type !== null)
                            <span class="badge badge-ghost badge-xs align-middle">{{ __('customer-material.source_lexoffice') }}</span>
                        @endif
                    </td>
                    <td class="text-sm text-base-content/70">{{ $allocation->project?->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $fmt($allocation->allocated_amount?->toFloat() ?? 0.0) }}</td>
                    <td class="text-right">
                        <x-action-form :action="route('customers.material-costs.destroy', [$customer, $allocation])" method="DELETE"
                                       :confirm="__('customer-material.confirm_delete')"
                                       confirm-icon="delete" confirm-tone="error" :confirm-label="__('customer-material.delete')">
                            <x-icon-btn icon="delete" type="submit" tone="error" size="xs" :label="__('customer-material.delete')" />
                        </x-action-form>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-card>
