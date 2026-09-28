{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfauftrag (MVP-938). Erwartet: $order, $canManage, $canInspect --}}
@extends('layouts.app')

@section('title', $order->title)
@section('nav-title', __('inspection_order.title'))

@php
    use App\Enums\AssetCompliance\AssetInspectionOrderStatus as S;
@endphp

@section('content')
<x-index-page :subtitle="$order->supplier?->name"
              back-route="asset-compliance.orders.index" :back-label="__('inspection_order.title')">
    <x-slot:actions>
        @if ($canManage && in_array(S::Cancelled, $order->status->allowedTransitions(), true))
            <form method="POST" action="{{ route('asset-compliance.orders.cancel', $order) }}" data-confirm-dialog data-confirm-message="{{ __('inspection_order.confirm_cancel') }}" data-confirm-tone="error">
                @csrf
                <x-button type="submit" tone="ghost" class="text-error">{{ __('inspection_order.cancel') }}</x-button>
            </form>
        @endif
    </x-slot:actions>

    <x-card>
        <x-detail-grid class="grid-cols-2">
            <x-detail-grid.row :label="__('inspection_order.field.status')">{{ $order->status->label() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inspection_order.field.recipient_email')">{{ $order->recipient_email }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inspection_order.field.offer_amount')">{{ $order->offer_amount !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($order->offer_amount, 2, withThousandsSeparator: true) . ' ' . $order->currency : '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('inspection_order.field.offer_planned_on')">{{ $order->offer_planned_on?->format('d.m.Y') ?? '—' }}</x-detail-grid.row>
        </x-detail-grid>
        @if ($order->offer_note)<p class="mt-2 whitespace-pre-line text-sm">{{ $order->offer_note }}</p>@endif
        @if ($canManage && $order->status === S::Offered)
            <form method="POST" action="{{ route('asset-compliance.orders.decide', $order) }}" class="mt-3 flex justify-end gap-2">
                @csrf
                <button type="submit" name="decision" value="reject" class="btn btn-sm btn-outline">{{ __('inspection_order.reject') }}</button>
                <button type="submit" name="decision" value="accept" class="btn btn-sm btn-primary">{{ __('inspection_order.accept') }}</button>
            </form>
        @endif
    </x-card>

    <x-card padding="p-0" class="mt-4" :title="__('inspection_order.field.items')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('inspection_order.field.asset') }}</th>
                    <th>{{ __('inspection_order.field.result') }}</th>
                    <th>{{ __('inspection_order.field.performed_on') }}</th>
                    <th>{{ __('inspection_order.field.valid_until') }}</th>
                    <th>{{ __('inspection_order.field.certificate_no') }}</th>
                    <th class="text-right">{{ __('inspection_order.field.event') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->asset?->name }} <span class="text-muted">({{ $item->asset?->asset_no }})</span></td>
                    <td>{{ $item->result?->label() ?? '—' }}</td>
                    <td>{{ $item->performed_on?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $item->valid_until?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $item->certificate_no ?? '—' }}@if ($item->attachments->isNotEmpty()) <x-icon name="attach_file" class="text-muted" />@endif</td>
                    <td class="text-right">{{ $item->asset_inspection_event_id ? __('inspection_order.taken_over') : '—' }}</td>
                </tr>
            @endforeach
        </x-table>
        @if ($canInspect && $order->status === S::Reported)
            <form method="POST" action="{{ route('asset-compliance.orders.take-over', $order) }}" class="flex justify-end p-3">
                @csrf
                <x-button type="submit" size="sm">{{ __('inspection_order.take_over') }}</x-button>
            </form>
        @endif
    </x-card>
</x-index-page>
@endsection
