<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceHandoverService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Handover;

use App\Enums\Finance\BillingMode;
use App\Enums\Lexoffice\LexofficeHandoverStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Invoice;
use App\Models\Platform\{Organization, User};
use App\Models\Plugins\Lexoffice\LexofficeInvoiceHandover;
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Plugins\Lexoffice\Tariff\LexwareTariffService;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Support\CsvExport;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\{CryptoHelper, NumberHelper};
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Übergabeliste lokal ausgestellter Belege an Lexware (Feature 158, MVP-833):
 * Kandidaten sind ausgestellte Belege lokal geführter Kunden, die nicht
 * bereits über {@see LexofficeInvoiceService::publish()} in Lexware entstanden
 * sind. Der manuelle Weg exportiert das eingefrorene Original (PDF mit Hash)
 * samt Zuordnungsliste und lässt die Übergabe mit Benutzer und Zeitpunkt
 * bestätigen. „Exportiert" ≠ „bestätigt" ≠ „übertragen" ≠ „gebucht".
 */
class LexofficeInvoiceHandoverService {
    public function __construct(private readonly InvoicePdfRenderer $pdf) {}

    /**
     * Ausgestellte lokale Belege der Organisation im Zeitraum (Belegdatum),
     * ohne die in Lexware erzeugten.
     *
     * @return Builder<Invoice>
     */
    public function candidates(Organization $organization, CarbonInterface $from, CarbonInterface $to): Builder {
        $orgExternal = BillingMode::tryFrom((string) data_get($organization->settings, 'billing_mode', ''))?->isExternal() ?? false;

        return Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->whereBetween('issued_on', DateRange::days($from, $to))
            ->whereNotIn('id', ExternalReference::query()
                ->select('referenceable_id')
                ->where('plugin_id', LexofficePlugin::ID)
                ->where('external_type', LexofficeInvoiceService::EXT_TYPE_INVOICE)
                ->where('referenceable_type', (new Invoice)->getMorphClass()))
            ->whereHas('customer', function (Builder $q) use ($orgExternal): void {
                if ($orgExternal) {
                    $q->where('billing_mode', BillingMode::Workdiary->value);
                } else {
                    $q->where(fn (Builder $w) => $w->whereNull('billing_mode')->orWhere('billing_mode', BillingMode::Workdiary->value));
                }
            })
            ->with(['customer:id,name,company', 'dispatches'])
            ->orderByDesc('issued_on')
            ->orderByDesc('id');
    }

    /**
     * Übergabestände je Rechnung.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, LexofficeInvoiceHandover>
     */
    public function statesFor(array $invoiceIds): array {
        if ($invoiceIds === []) {
            return [];
        }

        return LexofficeInvoiceHandover::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->with(['exporter:id,name', 'confirmer:id,name'])
            ->get()
            ->keyBy('invoice_id')
            ->all();
    }

    public function stateFor(Invoice $invoice): ?LexofficeInvoiceHandover {
        return LexofficeInvoiceHandover::query()->where('invoice_id', $invoice->id)->first();
    }

    /**
     * Export: das ausgestellte Original als PDF (Design-Pipeline), je Beleg
     * ein Hash, dazu die Zuordnungsliste. Markiert „exportiert", stuft eine
     * bereits bestätigte oder übertragene Übergabe aber nicht zurück.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, string> Dateiname → Bytes
     */
    public function export(Organization $organization, Collection $invoices, User $actor): array {
        if ($invoices->isEmpty()) {
            throw new RuntimeException((string) __('lexware.error.nothing_selected'));
        }

        $files = [];
        $rows = [];
        foreach ($invoices as $invoice) {
            if ((int) $invoice->organization_id !== (int) $organization->id || $invoice->status === Invoice::STATUS_DRAFT) {
                throw new RuntimeException((string) __('lexware.error.not_exportable', ['number' => (string) $invoice->number]));
            }
            $bytes = $this->pdf->output($invoice);
            $hash = (string) CryptoHelper::hash($bytes);
            $name = File::sanitizeDisplayName(($invoice->number ?: 'beleg-' . $invoice->id) . '.pdf');
            $files[$name] = $bytes;

            $handover = DB::transaction(function () use ($organization, $invoice, $actor, $hash): LexofficeInvoiceHandover {
                /** @var LexofficeInvoiceHandover $handover */
                $handover = LexofficeInvoiceHandover::query()->firstOrNew(['invoice_id' => $invoice->id]);
                $handover->organization_id = $organization->id;
                $handover->channel = LexwareTariffService::CHANNEL_MANUAL;
                $handover->invoice_number = (string) $invoice->number;
                $handover->document_sha256 = $hash;
                $handover->exported_at = now();
                $handover->exported_by = $actor->id;
                if (! $handover->exists || ! $handover->status->isSettled()) {
                    $handover->status = LexofficeHandoverStatus::Exported;
                }
                $handover->save();

                return $handover;
            });

            $invoice->audit('invoice.lexwareHandover.exported', ['handover_id' => $handover->id, 'sha256' => $hash, 'by' => $actor->id]);

            $rows[] = [
                (string) $invoice->number,
                $invoice->issued_on?->toDateString() ?? '',
                $invoice->due_on?->toDateString() ?? '',
                (string) $invoice->customer->name,
                (string) ($invoice->customer->number ?? ''),
                (string) __('values.' . $invoice->status),
                (string) $invoice->currency->value,
                NumberHelper::toGermanFormat($invoice->subtotal?->toFloat() ?? 0.0, 2),
                NumberHelper::toGermanFormat($invoice->tax_amount?->toFloat() ?? 0.0, 2),
                NumberHelper::toGermanFormat($invoice->total?->toFloat() ?? 0.0, 2),
                $name,
                $hash,
                $handover->status->label(),
            ];
        }

        $files['zuordnungsliste.csv'] = CsvExport::toString(
            [
                (string) __('lexware.csv.number'), (string) __('lexware.csv.issued_on'), (string) __('lexware.csv.due_on'),
                (string) __('lexware.csv.customer'), (string) __('lexware.csv.customer_number'), (string) __('lexware.csv.status'),
                (string) __('lexware.csv.currency'), (string) __('lexware.csv.net'), (string) __('lexware.csv.tax'), (string) __('lexware.csv.gross'),
                (string) __('lexware.csv.file'), 'SHA-256', (string) __('lexware.csv.handover'),
            ],
            $rows,
        );

        return $files;
    }

    /** Manuelle Bestätigung — braucht Benutzer und Zeitpunkt, ersetzt keine technische Empfangsbestätigung. */
    public function confirm(Invoice $invoice, User $actor, ?string $note): LexofficeInvoiceHandover {
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            throw new RuntimeException((string) __('lexware.error.not_exportable', ['number' => (string) $invoice->number]));
        }

        return DB::transaction(function () use ($invoice, $actor, $note): LexofficeInvoiceHandover {
            /** @var LexofficeInvoiceHandover $handover */
            $handover = LexofficeInvoiceHandover::query()->firstOrNew(['invoice_id' => $invoice->id]);
            if ($handover->exists && $handover->status === LexofficeHandoverStatus::Transferred) {
                throw new RuntimeException((string) __('lexware.error.already_transferred'));
            }
            $handover->organization_id = (int) $invoice->organization_id;
            $handover->channel = $handover->channel ?: LexwareTariffService::CHANNEL_MANUAL;
            $handover->invoice_number = (string) $invoice->number;
            $handover->status = LexofficeHandoverStatus::Confirmed;
            $handover->confirmed_at = now();
            $handover->confirmed_by = $actor->id;
            $handover->confirmation_note = filled($note) ? trim((string) $note) : null;
            $handover->save();

            $invoice->audit('invoice.lexwareHandover.confirmed', ['handover_id' => $handover->id, 'note' => $handover->confirmation_note, 'by' => $actor->id]);

            return $handover;
        });
    }
}
