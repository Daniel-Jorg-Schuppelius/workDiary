{{--
    Kombinierte Beleg-/Rechnungssicht eines Kontakts (Kunde ODER Lieferant):
    lokale Rechnungen (App\Models\Invoice) UND Lexoffice-Belege
    (Rechnungen/Angebote/Aufträge/Lieferscheine …) in einer Tabelle, nach Typ
    gruppiert und auf den globalen Header-Zeitraum eingegrenzt.

    Erwartete Variablen:
      $invoices    — Collection<Invoice>        (lokale Rechnungen, bereits zeitraumgefiltert; ggf. leer)
      $vouchers    — Collection<LexofficeVoucher> (bereits zeitraumgefiltert; ggf. leer)
      $plugin      — Lexoffice-Plugin-Instanz (oder null)
      $contactRef  — ExternalReference des verknüpften Lexoffice-Kontakts (oder null)
      $range       — array{label: string, ...} aus globalDateRange()
      $syncRoute   — (optional) Route zum Beleg-Sync
      $placeholder — (optional) bool; true → Sektion auch ohne Belege als
                     Leer-Zustand zeigen (z. B. Kunden mit Rechnungsrecht)
--}}
@php
    $lexofficeConnected = $plugin && $plugin->isEnabled() && $contactRef;

    // Auch ohne Lexoffice-Verknüpfung als feste Sektion zeigen, sofern der
    // Nutzer überhaupt Rechnungen sehen darf (sonst nur bei vorhandenen Daten).
    $alwaysShow = ($placeholder ?? false) && (auth()->user()?->can('viewAny', \App\Models\Invoice::class) ?? false);

    $valueLabel = static function (?string $value, string $empty = '–'): string {
        if ($value === null || $value === '') {
            return $empty;
        }
        $key = 'values.' . $value;
        $label = __($key);

        return $label === $key ? $value : $label;
    };

    // Status-Tönung für lokale Invoice-Status UND Lexoffice-Voucher-Status.
    $statusTone = static fn(?string $status): string => match ($status) {
        'paid', 'paidoff', 'accepted', 'transferred', 'checked' => 'success',
        'issued', 'sent' => 'info',
        'open', 'draft', 'unchecked' => 'warning',
        'overdue', 'rejected' => 'error',
        'cancelled', 'voided' => 'ghost',
        default => 'neutral',
    };

    // Beide Quellen auf eine gemeinsame Zeilenform normalisieren.
    $rows = collect();

    foreach ($invoices as $invoice) {
        $rows->push([
            'type' => $invoice->type,
            'source' => 'local',
            'number' => $invoice->number,
            'date' => $invoice->issued_on,
            'status' => $invoice->status,
            'amount' => $invoice->total?->toFloat() ?? 0.0,
            'currency' => $invoice->currency->value,
            'model' => $invoice,
        ]);
    }

    foreach ($vouchers as $voucher) {
        $rows->push([
            'type' => $voucher->voucher_type,
            'source' => 'lexoffice',
            'number' => $voucher->voucher_number,
            'date' => $voucher->voucher_date,
            'status' => $voucher->voucher_status,
            'amount' => ($voucher->total_amount?->toFloat() ?? 0.0),
            'currency' => $voucher->currency->value,
            'model' => $voucher,
        ]);
    }

    // Reihenfolge der Typ-Badges (Rechnungen zuerst, dann Gutschriften, Angebote …).
    $typeOrder = ['invoice', 'salesinvoice', 'purchaseinvoice', 'credit_note', 'quotation', 'orderconfirmation', 'deliverynote'];
    $byType = $rows->groupBy('type')->sortBy(fn($g, $type) => ($i = array_search($type, $typeOrder, true)) === false ? 99 : $i);

    $rows = $rows->sortByDesc(fn($r) => optional($r['date'])->format('Y-m-d') ?? '');
    // Rechnungssumme: Ausgangs- (invoice/salesinvoice) UND Eingangsrechnungen
    // (purchaseinvoice, z. B. bei Lieferanten); stornierte Belege zählen nicht.
    $invoiceSum = $rows
        ->whereIn('type', ['invoice', 'salesinvoice', 'purchaseinvoice'])
        ->whereNotIn('status', ['voided', 'cancelled'])
        ->sum('amount');
@endphp

@if ($lexofficeConnected || $rows->isNotEmpty() || $alwaysShow)
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="receipt_long" class="text-base-content/60" /> {{ __('Rechnungen & Belege') }}
                <span class="badge badge-ghost badge-sm">{{ $range['label'] }}</span>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm text-base-content/60">
                    {{ __('Rechnungssumme') }}:
                    <span class="font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoiceSum, 2, withThousandsSeparator: true) }}&nbsp;&euro;</span>
                </span>
                @isset($syncRoute)
                    @if ($lexofficeConnected)
                        <form method="POST" action="{{ $syncRoute }}">
                            @csrf
                            <x-icon-btn icon="sync" size="xs" tone="ghost" type="submit" :title="__('Belege synchronisieren')" />
                        </form>
                    @endif
                @endisset
            </div>
        </div>

        @if ($byType->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach ($byType as $type => $group)
                    <span class="badge badge-sm badge-outline">{{ $valueLabel($type) }}: {{ $group->count() }}</span>
                @endforeach
            </div>
        @endif

        @if ($rows->isEmpty())
            <x-empty-state compact wide
                icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>'
                :title="__('Keine Belege im gewählten Zeitraum')"
                :message="$plugin && $plugin->isEnabled() && ! $contactRef
                    ? __('Kein Lexoffice-Kontakt verknüpft — verknüpfte Belege erscheinen erst nach Verknüpfung und Synchronisierung.')
                    : __('Für den im Kopf gewählten Zeitraum (:range) wurden keine Rechnungen oder Belege gefunden.', ['range' => $range['label']])" />
            {{-- Kein zusätzlicher Sync-Button hier: Aktualisieren läuft über den
                 Button oben in der Karten-Ecke (und stündlich automatisch per Cron). --}}
        @else
            <x-table table-sort="client">
                <x-slot:head>
                    <x-table.th sort type="string">{{ __('Nummer') }}</x-table.th>
                    <x-table.th sort type="date" default="desc">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Quelle') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
                    <x-table.th align="right"></x-table.th>
                </x-slot:head>
                @foreach ($rows as $row)
                    @php $model = $row['model']; @endphp
                    <tr>
                        <td class="font-mono text-xs">
                            @if ($row['source'] === 'local')
                                <a href="{{ route('invoices.show', $model) }}" class="link">{{ $row['number'] ?? '–' }}</a>
                            @else
                                {{ $row['number'] ?? '–' }}
                            @endif
                        </td>
                        <td data-sort-value="{{ optional($row['date'])->format('Y-m-d') ?? '' }}">{{ optional($row['date'])->fdate() ?? '–' }}</td>
                        <td>{{ $valueLabel($row['type']) }}</td>
                        <td>
                            <span class="badge badge-sm {{ $row['source'] === 'local' ? 'badge-primary badge-outline' : 'badge-ghost' }}">
                                {{ $row['source'] === 'local' ? __('Lokal') : 'Lexoffice' }}
                            </span>
                        </td>
                        <td>
                            <x-status-badge :tone="$statusTone($row['status'])">{{ $valueLabel($row['status']) }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['amount'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['amount'], 2, withThousandsSeparator: true) }}&nbsp;{{ $row['currency'] }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @if ($row['source'] === 'local')
                                    <x-icon-btn icon="visibility" size="xs"
                                                :href="route('invoices.show', $model)"
                                                :label="__('Rechnung anzeigen')" />
                                    <x-icon-btn icon="download" size="xs"
                                                :href="route('invoices.pdf', $model)"
                                                :label="__('PDF herunterladen')" />
                                @else
                                    @if (in_array($row['type'], ['invoice', 'salesinvoice'], true) && $row['status'] === 'overdue')
                                        <form method="POST" action="{{ route('lexoffice.vouchers.dunning', $model) }}">
                                            @csrf
                                            <x-icon-btn icon="notification_important" size="xs" tone="warning" type="submit" :title="__('Mahnung erstellen')" />
                                        </form>
                                    @endif
                                    <x-icon-btn icon="visibility" size="xs"
                                                :href="route('lexoffice.vouchers.preview', $model)"
                                                data-entry-modal-trigger
                                                :label="__('Belegbild anzeigen')" />
                                    <x-icon-btn icon="download" size="xs"
                                                :href="route('lexoffice.vouchers.file', [$model, 'download' => 1])"
                                                :label="__('Belegbild herunterladen')" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
@endif
