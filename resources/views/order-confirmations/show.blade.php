{{--
  Created on   : Tue Oct 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auftragsbestätigung als eigener Beleg (Phase 145, MVP-1162). --}}
@extends('layouts.app')

@section('title', __('Auftragsbestätigung') . ' ' . $confirmation->number)
@section('nav-title', $confirmation->number)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('Auftragsbestätigung') . ' ' . $confirmation->number" :badge="$confirmation->status->label()" badge-tone="outline">
            <div class="text-sm text-base-content/70">{{ $confirmation->customer?->name }}</div>
            @if ($confirmation->confirmed_on !== null)
                <div class="text-sm text-base-content/70">{{ __('quotes.order_confirmation.confirmed_on', ['date' => $confirmation->confirmed_on->fdate()]) }}</div>
            @endif
            <x-slot:actions>
                @can('confirm', $confirmation)
                    <x-action-form :action="route('order-confirmations.confirm', $confirmation)">
                        <x-icon-btn icon="verified" tone="primary" size="sm" type="submit" placement="bar" show-label>{{ __('quotes.order_confirmation.confirm') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @if ($confirmation->status === \App\Enums\Sales\OrderConfirmationStatus::Confirmed)
                    <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('order-confirmations.pdf', $confirmation)" show-label>{{ __('PDF') }}</x-icon-btn>
                    <x-icon-btn icon="forward_to_inbox" size="sm" data-entry-modal-trigger
                                :href="route('order-confirmations.mail.form', $confirmation)" show-label>{{ __('Per E-Mail senden') }}</x-icon-btn>
                    @can('viewAny', \App\Models\Billing\DeliveryNote::class)
                        <x-action-form :action="route('order-confirmations.delivery-note.store', $confirmation)">
                            <x-icon-btn icon="local_shipping" size="sm" type="submit" show-label>{{ __('billing.delivery_note.create') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @endif
                @if ($confirmation->quote !== null)
                    <x-icon-btn icon="request_quote" size="sm" placement="menu" :href="route('quotes.show', $confirmation->quote)" show-label>{{ __('quotes.order_confirmation.to_quote', ['number' => $confirmation->quote->number]) }}</x-icon-btn>
                @endif
                @can('cancel', $confirmation)
                    <x-action-form :action="route('order-confirmations.cancel', $confirmation)"
                          :confirm="__('quotes.order_confirmation.cancel_confirm')"
                          confirm-icon="cancel"
                          confirm-tone="error"
                          :confirm-label="__('quotes.order_confirmation.cancel')">
                        <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" placement="danger" show-label>{{ __('quotes.order_confirmation.cancel') }}</x-icon-btn>
                    </x-action-form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($numberIssuer !== null)
        <div role="status" class="alert alert-info text-sm">
            <x-icon name="tag" />
            {{ __('quotes.order_confirmation.number_pending', ['program' => $numberIssuer]) }}
        </div>
    @endif

    <x-card :title="__('Positionen')" padding="p-0">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>{{ __('Beschreibung') }}</th>
                    <th class="text-right">{{ __('Menge') }}</th>
                    <th class="text-right">{{ __('Einzelpreis') }}</th>
                    <th class="text-right">{{ __('Betrag') }}</th>
                </tr>
            </x-slot:head>
            @foreach (\App\Services\Billing\DocumentOutline::rows($confirmation->items, fn ($line): bool => $line->lineKind()->isPriced()) as $outlineRow)
                @if ($outlineRow['type'] === 'subtotal')
                    <tr>
                        <td></td>
                        <td colspan="3" class="text-right text-xs">{{ __('invoicing.line_kind.subtotal', ['number' => $outlineRow['number'], 'title' => $outlineRow['title']->description]) }}</td>
                        <td class="text-right font-semibold">{{ $outlineRow['amount']->format() }}</td>
                    </tr>
                    @continue
                @endif
                @php $item = $outlineRow['line']; @endphp
                @if (! $item->lineKind()->isPriced())
                    <tr>
                        <td>{{ $outlineRow['number'] }}</td>
                        <td colspan="4" class="{{ $item->lineKind() === \App\Enums\Billing\DocumentLineKind::Title ? 'font-semibold' : 'italic whitespace-pre-line' }}">{{ $item->description }}</td>
                    </tr>
                    @continue
                @endif
                <tr>
                    <td>{{ $outlineRow['number'] }}</td>
                    <td class="whitespace-pre-line">{{ $item->description }}</td>
                    <td class="text-right tabular-nums">{{ \App\Support\DocumentNumber::decimal((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                    <td class="text-right tabular-nums">{{ $item->unit_price?->format() }}</td>
                    <td class="text-right tabular-nums">{{ $item->netAmount()->format() }}</td>
                </tr>
            @endforeach
            <x-slot:foot>
                <tr><td colspan="4" class="text-right">{{ __('Zwischensumme (netto)') }}</td><td class="text-right tabular-nums">{{ $confirmation->subtotal?->format() }}</td></tr>
                <tr><td colspan="4" class="text-right">{{ __('USt.') }}</td><td class="text-right tabular-nums">{{ $confirmation->tax_amount?->format() }}</td></tr>
                <tr><td colspan="4" class="text-right font-semibold">{{ __('Gesamt') }}</td><td class="text-right tabular-nums font-semibold">{{ $confirmation->total?->format() }}</td></tr>
            </x-slot:foot>
        </x-table>
    </x-card>

    <x-dispatch-history :kinds="[\App\Enums\DocumentDesign\RenderDocumentKind::OrderConfirmation]" :ids="[(int) $confirmation->id]" :since="$confirmation->created_at" />

    {{-- Zugehörige Belege lokal und im Buchhaltungsprogramm (MVP-1159). --}}
    <x-billing.document-relations :document="$confirmation" />
</x-page-shell>
@endsection
