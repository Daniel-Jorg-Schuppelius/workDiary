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
                <div class="font-bold">{{ __('Storniert') }}@if ($invoice->cancelled_at) – {{ $invoice->cancelled_at->format('d.m.Y H:i') }}@endif</div>
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

    @php($childCredits = $invoice->isCreditNote() ? collect() : $invoice->creditNotes()->get())
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
                'date' => $invoice->sent_at->format('d.m.Y H:i'),
                'count' => $invoice->sent_count,
            ]) }}
        </div>
    @endif

    <x-page-toolbar :title="$invoice->documentLabel() . ' ' . $invoice->number" :badge="__($invoice->status)" badge-tone="outline">
        <div class="text-sm text-base-content/70">{{ $invoice->customer->name }}</div>
        @if ($invoice->hasServicePeriod())
            <div class="text-sm text-base-content/70">{{ $invoice->dateLabelPeriod() }}: {{ $invoice->serviceDateFrom()->format('d.m.Y') }} – {{ $invoice->serviceDateTo()->format('d.m.Y') }}</div>
        @elseif ($invoice->serviceDateSingle())
            <div class="text-sm text-base-content/70">{{ $invoice->dateLabelSingle() }}: {{ $invoice->serviceDateSingle()->format('d.m.Y') }}</div>
        @endif
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('invoices.pdf', $invoice)" show-label>{{ __('PDF') }}</x-icon-btn>
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
                <form method="POST" action="{{ route('invoices.issue', $invoice) }}" class="inline">@csrf
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Stellen') }}</x-icon-btn>
                </form>
                {{-- Plugin-Slot: jedes aktive Plugin kann hier eigene Aktionen (z. B. "An Lexoffice senden") einklinken --}}
                {!! app(\App\Plugins\PluginManager::class)->renderSlot('invoice-show.actions', $invoice) !!}
            @endcan
            @can('pay', $invoice)
                <form method="POST" action="{{ route('invoices.pay', $invoice) }}" class="inline">@csrf
                    <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit" show-label>{{ __('Bezahlt markieren') }}</x-icon-btn>
                </form>
            @endcan
            @can('cancel', $invoice)
                <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="inline"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Rechnung wirklich stornieren?') }}"
                      data-confirm-icon="block"
                      data-confirm-tone="warning"
                      data-confirm-label="{{ __('Stornieren') }}">
                    @csrf
                    <x-icon-btn icon="block" tone="warning" size="sm" type="submit" show-label>{{ __('Stornieren') }}</x-icon-btn>
                </form>
            @endcan
            @can('createCreditNote', $invoice)
                <form method="POST" action="{{ route('invoices.credit-note', $invoice) }}" class="inline"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Korrekturrechnung (Gutschrift) zu :nr erstellen?', ['nr' => $invoice->number]) }}"
                      data-confirm-icon="undo"
                      data-confirm-tone="warning"
                      data-confirm-label="{{ __('Korrekturrechnung erstellen') }}">
                    @csrf
                    <x-icon-btn icon="undo" tone="warning" size="sm" type="submit" show-label>{{ __('Korrekturrechnung') }}</x-icon-btn>
                </form>
            @endcan
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="inline"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Wirklich löschen?') }}"
                      data-confirm-icon="delete"
                      data-confirm-tone="error"
                      data-confirm-label="{{ __('Löschen') }}">
                    @csrf @method('DELETE')
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                </form>
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
            <tr><td colspan="{{ $footColspan }}" class="text-right font-bold">{{ __('Gesamt') }}</td><td class="text-right font-bold">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        </x-slot:foot>
        @forelse ($invoice->items as $item)
            <tr>
                <td>{{ $item->position }}</td>
                <td>{{ $item->description }}</td>
                @if ($showServiceDates)<td data-sort-value="{{ optional($item->service_date)->toDateString() }}">{{ optional($item->service_date)->format('d.m.Y') ?: '—' }}</td>@endif
                <td class="text-right" data-sort-value="{{ (float) $item->quantity }}">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->unit_price }}">{{ number_format((float) $item->unit_price, 2, ',', '.') }} {{ $invoice->currency }}</td>
                <td class="text-right" data-sort-value="{{ (float) $item->amount }}">{{ number_format((float) $item->amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
                @can('update', $invoice)
                    @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="edit" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('invoices.items.edit', [$invoice, $item])"
                                        :title="__('Bearbeiten')" />
                            <form method="POST" action="{{ route('invoices.items.destroy', [$invoice, $item]) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Position wirklich entfernen?') }}"
                                  data-confirm-icon="delete"
                                  data-confirm-tone="error"
                                  data-confirm-label="{{ __('Entfernen') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                            </form>
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
