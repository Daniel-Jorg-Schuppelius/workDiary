@extends('layouts.app')

@section('title', __('Rechnung :nr', ['nr' => $invoice->number]))
@section('nav-title', $invoice->number)

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($invoice->isCancelled())
        <div class="alert alert-error">
            <span class="material-symbols-outlined" aria-hidden="true">block</span>
            <div>
                <div class="font-bold">{{ __('Storniert') }}@if ($invoice->cancelled_at) – {{ $invoice->cancelled_at->fdatetime() }}@endif</div>
                @if ($invoice->cancel_reason)
                    <div class="text-sm">{{ $invoice->cancel_reason }}</div>
                @endif
            </div>
        </div>
    @endif

    @if ($invoice->isCreditNote() && $invoice->parent)
        <div class="alert alert-info">
            <span class="material-symbols-outlined" aria-hidden="true">undo</span>
            <div>
                {{ __('Korrekturrechnung (Gutschrift) zu') }}
                <a class="link" href="{{ route('invoices.show', $invoice->parent) }}">{{ $invoice->parent->number }}</a>
            </div>
        </div>
    @endif

    {{-- Blockform statt @php(...): Inline-@php + späterer @php…@endphp-Block in derselben Datei
         erzeugt über Blades storePhpBlocks ein ungültiges "<?php(" (kein Open-Tag) — View bricht. --}}
    @php $childCredits = $invoice->isCreditNote() ? collect() : $invoice->creditNotes()->get(); @endphp
    @if ($childCredits->isNotEmpty())
        <div class="alert alert-warning">
            <span class="material-symbols-outlined" aria-hidden="true">undo</span>
            <div>
                {{ __('Es existieren Korrekturrechnungen:') }}
                @foreach ($childCredits as $cn)
                    <a class="link" href="{{ route('invoices.show', $cn) }}">{{ $cn->number }}</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        </div>
    @endif

    @if ($invoice->sent_at)
        <div class="alert alert-success/40 text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">mark_email_read</span>
            {{ __('Zuletzt versendet: :date (:count Versand(e))', [
                'date' => $invoice->sent_at->fdatetime(),
                'count' => $invoice->sent_count,
            ]) }}
        </div>
    @endif

    <x-page-toolbar :title="$invoice->documentLabel() . ' ' . $invoice->number" :badge="__($invoice->status)" badge-tone="outline">
        <div class="text-sm text-base-content/70">{{ $invoice->customer->name }}</div>
        @if ($invoice->hasServicePeriod())
            <div class="text-sm text-base-content/70">{{ $invoice->dateLabelPeriod() }}: {{ $invoice->serviceDateFrom()->fdate() }} – {{ $invoice->serviceDateTo()->fdate() }}</div>
        @elseif ($invoice->serviceDateSingle())
            <div class="text-sm text-base-content/70">{{ $invoice->dateLabelSingle() }}: {{ $invoice->serviceDateSingle()->fdate() }}</div>
        @endif
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('invoices.pdf', $invoice)" show-label>{{ __('PDF') }}</x-icon-btn>
            {{-- E-Rechnung (Feature 045): XRechnung nur im Pfad „WorkDiary führt" und für gestellte/bezahlte Rechnungen. --}}
            @php $einvoiceVisible = in_array($invoice->status, [\App\Models\Invoice::STATUS_ISSUED, \App\Models\Invoice::STATUS_PAID], true) && ! app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer)->isExternal(); @endphp
            @if ($einvoiceVisible)
                <x-icon-btn icon="receipt" size="sm" :href="route('invoices.einvoice', $invoice)" show-label
                            :title="__('invoicing.einvoice.button_title')">{{ __('invoicing.einvoice.button') }}</x-icon-btn>
                <x-icon-btn icon="receipt" size="sm" :href="route('invoices.zugferd', $invoice)" show-label
                            :title="__('invoicing.einvoice.zugferd.button_title')">{{ __('invoicing.einvoice.zugferd.button') }}</x-icon-btn>
            @endif
            @can('send', $invoice)
                <x-icon-btn icon="mail" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('invoices.send.form', $invoice)"
                            show-label>{{ __('Per E-Mail senden') }}</x-icon-btn>
            @endcan
            @can('update', $invoice)
                @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('invoices.items.create', $invoice)"
                                show-label>{{ __('Position hinzufügen') }}</x-icon-btn>
                    <x-icon-btn icon="receipt_long" tone="info" size="sm"
                                data-entry-modal-trigger
                                :href="route('invoices.expenses.form', $invoice)"
                                show-label>{{ __('Spesen hinzufügen') }}</x-icon-btn>
                @endif
            @endcan
            @can('issue', $invoice)
                <x-action-form :action="route('invoices.issue', $invoice)">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Stellen') }}</x-icon-btn>
                </x-action-form>
                {{-- Plugin-Slot: jedes aktive Plugin kann hier eigene Aktionen (z. B. "An Lexoffice senden") einklinken --}}
                {!! app(\App\Plugins\PluginManager::class)->renderSlot('invoice-show.actions', $invoice) !!}
            @endcan
            @can('pay', $invoice)
                <x-action-form :action="route('invoices.pay', $invoice)">
                    <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" show-label>{{ __('Bezahlt markieren') }}</x-icon-btn>
                </x-action-form>
            @endcan
            @can('cancel', $invoice)
                <x-action-form :action="route('invoices.cancel', $invoice)"
                      :confirm="__('Rechnung wirklich stornieren?')"
                      confirm-icon="block"
                      confirm-tone="warning"
                      :confirm-label="__('Stornieren')">
                    <x-icon-btn icon="block" tone="warning" size="sm" type="submit" show-label>{{ __('Stornieren') }}</x-icon-btn>
                </x-action-form>
            @endcan
            @can('createCreditNote', $invoice)
                <x-action-form :action="route('invoices.credit-note', $invoice)"
                      :confirm="__('Korrekturrechnung (Gutschrift) zu :nr erstellen?', ['nr' => $invoice->number])"
                      confirm-icon="undo"
                      confirm-tone="warning"
                      :confirm-label="__('Korrekturrechnung erstellen')">
                    <x-icon-btn icon="undo" tone="warning" size="sm" type="submit" show-label>{{ __('Korrekturrechnung') }}</x-icon-btn>
                </x-action-form>
            @endcan
            @can('delete', $invoice)
                <x-action-form :action="route('invoices.destroy', $invoice)" method="DELETE"
                      :confirm="__('Wirklich löschen?')"
                      confirm-icon="delete"
                      confirm-tone="error"
                      :confirm-label="__('Löschen')">
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                </x-action-form>
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    @php $showServiceDates = $invoice->hasServicePeriod(); $footColspan = $showServiceDates ? 5 : 4; @endphp
    <x-table table-sort="client">
        <x-slot:head>
            <tr>
                <th>#</th>
                <x-table.th sort>{{ __('Beschreibung') }}</x-table.th>
                @if ($showServiceDates)<x-table.th sort type="date">{{ $invoice->dateLabelSingle() }}</x-table.th>@endif
                <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Einzelpreis') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
                @can('update', $invoice)
                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    @endif
                @endcan
            </tr>
        </x-slot:head>
        <x-slot:foot>
            <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('Zwischensumme') }}</td><td class="text-right">{{ number_format((float) $invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
            <tr><td colspan="{{ $footColspan }}" class="text-right">{{ __('USt.') }} {{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%</td><td class="text-right">{{ number_format((float) $invoice->tax_amount, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
            @if ($invoice->is_reverse_charge)
                <tr><td colspan="{{ $footColspan + 1 }}" class="text-right text-xs text-base-content/60">{{ __('Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).') }}</td></tr>
            @endif
            <tr><td colspan="{{ $footColspan }}" class="text-right font-bold">{{ __('Gesamt') }}</td><td class="text-right font-bold">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        </x-slot:foot>
        @forelse ($invoice->items as $item)
            <tr>
                <td>{{ $item->position }}</td>
                <td>{{ $item->description }}</td>
                @if ($showServiceDates)<td data-sort-value="{{ optional($item->service_date)->toDateString() }}">{{ optional($item->service_date)->fdate() ?: '—' }}</td>@endif
                <td class="text-right" data-sort-value="{{ (float) $item->quantity }}">{{ number_format((float) $item->quantity, ((int) round((float) $item->quantity * 1000)) % 10 !== 0 ? 3 : 2, ',', '.') }} {{ $item->unit }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->unit_price }}">{{ number_format((float) $item->unit_price, ((int) round((float) $item->unit_price * 10000)) % 100 !== 0 ? 4 : 2, ',', '.') }} {{ $invoice->currency }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->amount }}">{{ number_format((float) $item->amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
                @can('update', $invoice)
                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="edit" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('invoices.items.edit', [$invoice, $item])"
                                        :title="__('Bearbeiten')" />
                            <x-action-form :action="route('invoices.items.destroy', [$invoice, $item])" method="DELETE"
                                  :confirm="__('Position wirklich entfernen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Entfernen')">
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                            </x-action-form>
                        </td>
                    @endif
                @endcan
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="5" :title="__('Keine Positionen.')" compact />
        @endforelse
    </x-table>
</x-page-shell>
@endsection
