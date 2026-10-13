{{--
  Created on   : Tue Oct 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lieferschein als eigener Beleg (Phase 145, MVP-1163). --}}
@extends('layouts.app')

@section('title', __('manufacturing.delivery_note.title') . ' ' . $note->number)
@section('nav-title', $note->number)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('manufacturing.delivery_note.title') . ' ' . $note->number" :badge="$note->status->label()" badge-tone="outline">
            <div class="text-sm text-base-content/70">{{ $note->customer?->name ?? __('manufacturing.delivery_note.no_customer') }}</div>
            @if ($note->delivered_on !== null)
                <div class="text-sm text-base-content/70">{{ __('billing.delivery_note.delivered_on', ['date' => $note->delivered_on->fdate()]) }}</div>
            @endif
            <x-slot:actions>
                @can('deliver', $note)
                    <x-action-form :action="route('delivery-notes.deliver', $note)">
                        <x-icon-btn icon="local_shipping" tone="primary" size="sm" type="submit" placement="bar" show-label>{{ __('billing.delivery_note.deliver') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @if ($note->status === \App\Enums\Billing\DeliveryNoteStatus::Draft && ! $note->hasStockDeliveries())
                    @can('update', $note)
                        <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('delivery-notes.edit', $note)" show-label>{{ __('billing.delivery_note.edit') }}</x-icon-btn>
                    @endcan
                @endif
                @if ($note->status === \App\Enums\Billing\DeliveryNoteStatus::Delivered)
                    <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('delivery-notes.pdf', $note)" target="_blank" show-label>{{ __('PDF') }}</x-icon-btn>
                    @can('update', $note)
                        <x-icon-btn icon="forward_to_inbox" size="sm" data-entry-modal-trigger
                                    :href="route('delivery-notes.mail.form', $note)" show-label>{{ __('Per E-Mail senden') }}</x-icon-btn>
                    @endcan
                @endif
                @if ($note->orderConfirmation !== null)
                    <x-icon-btn icon="verified" size="sm" placement="menu" :href="route('order-confirmations.show', $note->orderConfirmation)" show-label>{{ __('quotes.order_confirmation.open', ['number' => $note->orderConfirmation->number]) }}</x-icon-btn>
                @endif
                @if ($note->manufacturingOrder !== null)
                    <x-icon-btn icon="precision_manufacturing" size="sm" placement="menu" :href="route('manufacturing-orders.show', $note->manufacturingOrder)" show-label>{{ __('billing.delivery_note.to_order', ['number' => $note->manufacturingOrder->number]) }}</x-icon-btn>
                @endif
                @can('cancel', $note)
                    <x-action-form :action="route('delivery-notes.cancel', $note)"
                          :confirm="__('billing.delivery_note.cancel_confirm')"
                          confirm-icon="cancel"
                          confirm-tone="error"
                          :confirm-label="__('billing.delivery_note.cancel')">
                        <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" placement="danger" show-label>{{ __('billing.delivery_note.cancel') }}</x-icon-btn>
                    </x-action-form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($numberIssuer !== null)
        <div role="status" class="alert alert-info text-sm">
            <x-icon name="tag" />
            {{ __('billing.delivery_note.number_pending', ['program' => $numberIssuer]) }}
        </div>
    @endif

    <x-card :title="__('Positionen')" padding="p-0">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>{{ __('manufacturing.delivery_note.col.sku') }}</th>
                    <th>{{ __('manufacturing.delivery_note.col.name') }}</th>
                    <th class="text-right">{{ __('manufacturing.delivery_note.col.qty') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($note->items as $item)
                @if ($item->line_kind !== \App\Enums\Billing\DocumentLineKind::Item)
                    <tr>
                        <td>{{ $item->position }}</td>
                        <td colspan="3" class="{{ $item->line_kind === \App\Enums\Billing\DocumentLineKind::Title ? 'font-semibold' : 'italic whitespace-pre-line' }}">{{ $item->description }}</td>
                    </tr>
                    @continue
                @endif
                <tr>
                    <td>{{ $item->position }}</td>
                    <td class="font-mono text-xs">{{ $item->sku }}</td>
                    <td class="whitespace-pre-line">{{ $item->description }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::trimTrailingZeros(\CommonToolkit\ValueObjects\Decimal::of((string) $item->quantity, 4)->format(), ',') }} {{ $item->unit }}</td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    @if (filled($note->note))
        <x-card :title="__('Notiz')">
            <p class="text-sm whitespace-pre-line">{{ $note->note }}</p>
        </x-card>
    @endif

    <x-dispatch-history :kinds="[\App\Enums\DocumentDesign\RenderDocumentKind::DeliveryNote]" :ids="[(int) $note->id]" />

    {{-- Zugehörige Belege lokal und im Buchhaltungsprogramm (MVP-1159). --}}
    <x-billing.document-relations :document="$note" />
</x-page-shell>
@endsection
