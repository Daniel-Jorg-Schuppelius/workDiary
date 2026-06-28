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
                    <th>{{ __('procurement.catalog.col.internal_article') }}</th>
                    <th>{{ __('procurement.field.supplier') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.old_ek') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.new_ek') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.sale') }}</th>
                    <th class="text-right">{{ __('procurement.alert.col.margin') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.actions') }}</th>
                </x-slot:head>
                @foreach ($alerts as $alert)
                    <tr>
                        <td>{{ $alert->article?->name ?: '—' }}</td>
                        <td class="text-sm">{{ $alert->supplier?->name ?: '—' }}</td>
                        <td class="text-right tabular-nums text-sm opacity-70">
                            {{ $alert->old_purchase_price !== null ? number_format((float) $alert->old_purchase_price, 2, ',', '.') : '—' }}
                        </td>
                        <td class="text-right tabular-nums font-medium">{{ number_format((float) $alert->new_purchase_price, 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">{{ number_format((float) $alert->sale_price, 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">
                            <span class="text-error font-medium">{{ rtrim(rtrim($alert->new_margin, '0'), '.') }} %</span>
                            @if ($alert->min_margin !== null)
                                <span class="opacity-50 text-xs">(min {{ rtrim(rtrim($alert->min_margin, '0'), '.') }} %)</span>
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
        <div class="mt-3">{{ $alerts->links() }}</div>
    @endif
</x-index-page>
@endsection
</content>
