<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevDocumentUploader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Services;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\Invoice;
use App\Plugins\DatevOnline\Api\DatevOnlineClientFactory;
use App\Plugins\DatevOnline\Enums\{DatevTransferKind, DatevTransferStatus};
use App\Plugins\DatevOnline\Models\{DatevOnlineConnection, DatevOnlineTransfer};
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Support\MorphMap;
use App\Support\Query\DateRange;
use Datev\API\Online\Endpoints\AccountingDocuments\DocumentsEndpoint;
use Datev\API\Online\OnlineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Belegbilder an DATEV Unternehmen online (accounting:documents, MVP-122):
 * ausgestellte Rechnungen als PDF („Rechnungsausgang“). Nur ab
 * `documents_since`, je Beleg einmal; Fehlschläge werden bis zu fünfmal erneut
 * versucht. Der Rechnungseingang geht über
 * {@see DatevOnlineIncomingInvoiceTarget} (MVP-1111).
 */
class DatevDocumentUploader {
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly DatevOnlineClientFactory $clients,
        private readonly InvoicePdfRenderer $pdf,
    ) {}

    /** @return array{transferred: int, failed: int} */
    public function uploadPending(DatevOnlineConnection $connection, int $limit = 100): array {
        if (! $connection->isReady() || ! $connection->is_documents_enabled) {
            return ['transferred' => 0, 'failed' => 0];
        }
        $since = ($connection->documents_since ?? $connection->connected_at ?? now())->copy()->startOfDay();
        $documents = new DocumentsEndpoint($this->clients->for($connection, OnlineService::AccountingDocuments), (string) $connection->datev_client_number);
        $counts = ['transferred' => 0, 'failed' => 0];

        $invoices = $this->pending(Invoice::query(), $connection, DatevTransferKind::OutgoingDocument, Invoice::class)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
            ->where('type', '!=', Invoice::TYPE_PROFORMA)
            ->where('issued_on', '>=', DateRange::day($since))
            ->orderBy('id')->limit($limit)->get();
        foreach ($invoices as $invoice) {
            $this->upload($connection, $documents, DatevTransferKind::OutgoingDocument, $invoice->getMorphClass(), (int) $invoice->id, $counts,
                fn (): array => [$this->pdf->output($invoice), 'Rechnung-' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->number) . '.pdf', (string) $invoice->number]);
        }

        $connection->forceFill(['last_synced_at' => now()])->save();

        return $counts;
    }

    /**
     * Quellen ohne erfolgreiche Übertragung (und ohne ausgeschöpfte Versuche).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $class
     * @return Builder<TModel>
     */
    private function pending(Builder $query, DatevOnlineConnection $connection, DatevTransferKind $kind, string $class): Builder {
        $alias = MorphMap::alias($class);

        return $query->where('organization_id', $connection->organization_id)
            ->whereNotExists(static function ($sub) use ($connection, $kind, $alias, $query): void {
                $sub->selectRaw('1')->from('datev_online_transfers')
                    ->where('datev_online_transfers.organization_id', $connection->organization_id)
                    ->where('datev_online_transfers.kind', $kind->value)
                    ->where('datev_online_transfers.source_type', $alias)
                    ->whereColumn('datev_online_transfers.source_id', $query->getModel()->getQualifiedKeyName())
                    ->where(static fn ($done) => $done->where('datev_online_transfers.status', '!=', DatevTransferStatus::Failed->value)
                        ->orWhere('datev_online_transfers.attempts', '>=', self::MAX_ATTEMPTS));
            });
    }

    /**
     * @param  array{transferred: int, failed: int}  $counts
     * @param  callable(): array{0: string, 1: string, 2: string}  $file  Inhalt, Dateiname, Notiz
     */
    private function upload(DatevOnlineConnection $connection, DocumentsEndpoint $documents, DatevTransferKind $kind, string $sourceType, int $sourceId, array &$counts, callable $file): void {
        $transfer = DatevOnlineTransfer::query()->firstOrNew([
            'organization_id' => $connection->organization_id,
            'kind' => $kind,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $transfer->fill(['datev_client_number' => (string) $connection->datev_client_number, 'attempts' => $transfer->attempts + 1]);

        try {
            [$content, $filename, $note] = $file();
            $document = $documents->upload($content, $filename, ['document_type' => $kind->documentType(), 'note' => mb_substr($note, 0, 255)]);
            $transfer->fill([
                'status' => DatevTransferStatus::Transferred,
                'datev_reference' => $document?->getId(),
                'error' => null,
                'transferred_at' => Carbon::now(),
            ])->save();
            $counts['transferred']++;
        } catch (Throwable $e) {
            $transfer->fill(['status' => DatevTransferStatus::Failed, 'error' => mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 300)])->save();
            $counts['failed']++;
        }
    }
}
