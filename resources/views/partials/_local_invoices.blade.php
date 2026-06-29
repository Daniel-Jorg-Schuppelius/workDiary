{{--
    Lokale Rechnungen (App\Models\Invoice) eines Kunden — auf den globalen
    Header-Zeitraum (issued_on) eingegrenzt. Ergänzt die Lexoffice-Belege zu
    einer vollständigen Rechnungssicht.

    Erwartete Variablen:
      $invoices — Collection<Invoice> (bereits zeitraumgefiltert)
      $range    — array{label: string, ...} aus globalDateRange()
--}}
@can('viewAny', App\Models\Invoice::class)
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="request_quote" class="text-base-content/60" /> {{ __('Rechnungen') }}
                <span class="badge badge-ghost badge-sm">{{ $range['label'] }}</span>
            </h2>
            <span class="text-sm text-base-content/60">
                {{ __('Summe') }}:
                <span class="font-semibold">{{ number_format((float) $invoices->sum('total'), 2, ',', '.') }}&nbsp;&euro;</span>
            </span>
        </div>

        @if ($invoices->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Keine Rechnungen im gewählten Zeitraum.') }}</p>
        @else
            <x-table table-sort="client">
                <x-slot:head>
                    <x-table.th sort type="string">{{ __('Nummer') }}</x-table.th>
                    <x-table.th sort type="date" default="desc">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Summe') }}</x-table.th>
                    <x-table.th align="right"></x-table.th>
                </x-slot:head>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td class="font-mono text-xs">
                            <a href="{{ route('invoices.show', $invoice) }}" class="link">{{ $invoice->number }}</a>
                        </td>
                        <td data-sort-value="{{ optional($invoice->issued_on)->format('Y-m-d') ?? '' }}">{{ optional($invoice->issued_on)->fdate() ?? '–' }}</td>
                        <td>{{ __("values.{$invoice->type}") }}</td>
                        <td>
                            <x-status-badge :tone="match ($invoice->status) {
                                'paid' => 'success',
                                'issued' => 'info',
                                'draft' => 'warning',
                                'cancelled' => 'ghost',
                                default => 'neutral',
                            }">{{ __("values.{$invoice->status}") }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $invoice->total }}">{{ number_format((float) $invoice->total, 2, ',', '.') }}&nbsp;{{ $invoice->currency }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                <x-icon-btn icon="visibility" size="xs"
                                            :href="route('invoices.show', $invoice)"
                                            :label="__('Rechnung anzeigen')" />
                                <x-icon-btn icon="download" size="xs"
                                            :href="route('invoices.pdf', $invoice)"
                                            :label="__('PDF herunterladen')" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
@endcan
