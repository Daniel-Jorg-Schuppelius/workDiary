{{--
  Created on   : Mon Jun 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _vouchers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Kombinierte Beleg-/Rechnungssicht eines Kontakts (Kunde ODER Lieferant).

    Hieß bis MVP-820 `_documents` und stand damit in der Akte neben dem
    Dokumente-Panel — zwei Karten, die nach demselben aussahen. Die Begriffe
    sind seither getrennt: **Beleg** ist Buchhaltung (hier), **Dokument** ist
    die verwaltete, versionierte Datei (`documents/_panel`), **Anhang** die
    lose Datei am Vorgang (`<x-attachments-section>`).

    Lokale Rechnungen und die Belege der Buchhaltungsprogramme
    ({@see \App\Services\Billing\PartyDocumentSources}, MVP-1038) in einer
    Tabelle, nach Typ gruppiert und auf den globalen Header-Zeitraum eingegrenzt.

    Erwartete Variablen:
      $invoices    — Collection<Invoice> (lokale Rechnungen, bereits zeitraumgefiltert; ggf. leer)
      $external    — list<PartyDocumentList> der aktiven Quellen
      $range       — array{label: string, ...} aus globalDateRange()
      $placeholder — (optional) bool; true → Sektion auch ohne Belege als
                     Leer-Zustand zeigen (z. B. Kunden mit Rechnungsrecht)
--}}
@php
    $linkedSources = collect($external)->filter(static fn ($list): bool => $list->linked);
    $unlinkedHint = collect($external)->first(static fn ($list): bool => ! $list->linked && $list->unlinkedHint !== null)?->unlinkedHint;

    // Auch ohne verknüpftes Buchhaltungsprogramm als feste Sektion zeigen, sofern der
    // Nutzer überhaupt Rechnungen sehen darf (sonst nur bei vorhandenen Daten).
    $alwaysShow = ($placeholder ?? false) && (auth()->user()?->can('viewAny', \App\Models\Invoicing\Invoice::class) ?? false);

    $valueLabel = static function (?string $value, string $empty = '–'): string {
        if ($value === null || $value === '') {
            return $empty;
        }
        $key = 'values.' . $value;
        $label = __($key);

        return $label === $key ? $value : $label;
    };

    // Status-Tönung für lokale Rechnungs- und Fremdbeleg-Status.
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
            'source' => null,
            'number' => $invoice->number,
            'date' => $invoice->issued_on,
            'status' => $invoice->status->value,
            'amount' => $invoice->total?->toFloat() ?? 0.0,
            'currency' => $invoice->currency->value,
            'model' => $invoice,
            'actions' => [],
        ]);
    }

    foreach ($external as $list) {
        foreach ($list->documents as $document) {
            $rows->push([
                'type' => $document->type,
                'source' => $list->source,
                'number' => $document->number,
                'date' => $document->date,
                'status' => $document->status,
                'amount' => $document->amount,
                'currency' => $document->currency,
                'model' => null,
                'actions' => $document->actions,
            ]);
        }
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

@if ($linkedSources->isNotEmpty() || $rows->isNotEmpty() || $alwaysShow)
    <x-card id="vouchers" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="receipt_long" class="text-muted" /> {{ __('Rechnungen & Belege') }}
                <x-status-badge>{{ $range['label'] }}</x-status-badge>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted">
                    {{ __('Rechnungssumme') }}:
                    <span class="font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoiceSum, 2, withThousandsSeparator: true) }}&nbsp;&euro;</span>
                </span>
                @foreach ($linkedSources as $list)
                    @if ($list->refresh)
                        <x-ui-action :action="$list->refresh" />
                    @endif
                @endforeach
            </div>
        </div>

        @if ($byType->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach ($byType as $type => $group)
                    <x-status-badge tone="plain" outline>{{ $valueLabel($type) }}: {{ $group->count() }}</x-status-badge>
                @endforeach
            </div>
        @endif

        @if ($rows->isEmpty())
            <x-empty-state compact wide
                icon="receipt_long"
                :title="__('Keine Belege im gewählten Zeitraum')"
                :message="$unlinkedHint ?? __('Für den im Kopf gewählten Zeitraum (:range) wurden keine Rechnungen oder Belege gefunden.', ['range' => $range['label']])" />
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
                            @if ($model)
                                <a href="{{ route('invoices.show', $model) }}" class="link">{{ $row['number'] ?? '–' }}</a>
                            @else
                                {{ $row['number'] ?? '–' }}
                            @endif
                        </td>
                        <td data-sort-value="{{ optional($row['date'])->format('Y-m-d') ?? '' }}">{{ optional($row['date'])->fdate() ?? '–' }}</td>
                        <td>{{ $valueLabel($row['type']) }}</td>
                        <td>
                            <x-status-badge :tone="$model ? 'primary' : 'ghost'" :outline="(bool) $model">
                                {{ $row['source'] ?? __('Lokal') }}
                            </x-status-badge>
                        </td>
                        <td>
                            <x-status-badge :tone="$statusTone($row['status'])">{{ $valueLabel($row['status']) }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $row['amount'] }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['amount'], 2, withThousandsSeparator: true) }}&nbsp;{{ $row['currency'] }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @if ($model)
                                    <x-icon-btn icon="visibility" size="xs"
                                                :href="route('invoices.show', $model)"
                                                :label="__('Rechnung anzeigen')" />
                                    <x-icon-btn icon="download" size="xs"
                                                :href="route('invoices.pdf', $model)"
                                                :label="__('PDF herunterladen')" />
                                @endif
                                @foreach ($row['actions'] as $action)
                                    <x-ui-action :action="$action" />
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
@endif
