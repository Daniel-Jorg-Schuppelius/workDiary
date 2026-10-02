<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevExtfTransferService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Services;

use App\Enums\Finance\DatevBatchStatus;
use App\Models\Finance\DatevBookingBatch;
use App\Plugins\DatevOnline\Api\DatevOnlineClientFactory;
use App\Plugins\DatevOnline\Enums\{DatevTransferKind, DatevTransferStatus};
use App\Plugins\DatevOnline\Exceptions\DatevOnlineException;
use App\Plugins\DatevOnline\Models\{DatevOnlineConnection, DatevOnlineTransfer};
use App\Services\Finance\DatevBookingService;
use Datev\API\Online\Endpoints\AccountingExtfFiles\ExtfFilesEndpoint;
use Datev\API\Online\OnlineService;
use Datev\Enums\Online\ExtfJobResult;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Buchungsstapel per EXTF-Import (accounting:extf-files) statt Download
 * (MVP-122). Übertragen wird die beim Abschluss gespeicherte Datei
 * unverändert; DATEV verarbeitet sie als Job, dessen Ergebnis der nächtliche
 * Lauf oder ein Klick abfragt.
 */
class DatevExtfTransferService {
    public function __construct(private readonly DatevOnlineClientFactory $clients) {}

    public function transfer(DatevOnlineConnection $connection, DatevBookingBatch $batch): DatevOnlineTransfer {
        if (! $connection->isReady()) {
            throw new DatevOnlineException('not_ready');
        }
        if ($batch->status !== DatevBatchStatus::Exported || $batch->file_path === null) {
            throw new DatevOnlineException('batch_not_exported');
        }
        // Der Stapelkopf nennt Berater und Mandant — er darf nur in genau diesen Mandanten.
        $clientId = $batch->advisor_number . '-' . $batch->client_number;
        if ($clientId !== $connection->datev_client_number) {
            throw new DatevOnlineException('client_mismatch');
        }

        $transfer = DatevOnlineTransfer::query()->firstOrNew([
            'organization_id' => $connection->organization_id,
            'kind' => DatevTransferKind::Extf,
            'source_type' => $batch->getMorphClass(),
            'source_id' => $batch->id,
        ]);
        if ($transfer->exists && $transfer->status !== DatevTransferStatus::Failed) {
            return $transfer;
        }

        $content = Storage::disk(DatevBookingService::DISK)->get($batch->file_path);
        if (! is_string($content) || $content === '') {
            throw new DatevOnlineException('file_missing');
        }

        $transfer->fill(['datev_client_number' => $clientId, 'attempts' => $transfer->attempts + 1]);
        try {
            $location = (new ExtfFilesEndpoint($this->clients->for($connection, OnlineService::AccountingExtfFiles), $clientId))
                ->import($content, basename($batch->file_path), 'wd-batch-' . $batch->id . '-' . substr((string) $batch->file_hash, 0, 12));
        } catch (Throwable $e) {
            $transfer->fill(['status' => DatevTransferStatus::Failed, 'error' => mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 300)])->save();

            throw new DatevOnlineException('transfer_failed');
        }

        $transfer->fill([
            'status' => DatevTransferStatus::Pending,
            'datev_reference' => $location?->getJobId(),
            'error' => null,
            'transferred_at' => now(),
        ])->save();

        return $transfer;
    }

    /** Ergebnis offener Importjobs abfragen; liefert die Zahl der abgeschlossenen. */
    public function refresh(DatevOnlineConnection $connection): int {
        $pending = DatevOnlineTransfer::query()
            ->where('organization_id', $connection->organization_id)
            ->where('kind', DatevTransferKind::Extf->value)
            ->where('status', DatevTransferStatus::Pending->value)
            ->whereNotNull('datev_reference')
            ->get();
        if ($pending->isEmpty() || ! $connection->isActive()) {
            return 0;
        }

        $done = 0;
        foreach ($pending as $transfer) {
            $endpoint = new ExtfFilesEndpoint($this->clients->for($connection, OnlineService::AccountingExtfFiles), $transfer->datev_client_number);
            $job = $endpoint->get((string) $transfer->datev_reference);
            $result = $job?->getResult();
            $transfer->checked_at = now();
            if ($result === ExtfJobResult::Succeeded || $result === ExtfJobResult::Failed) {
                $details = $job->getValidationDetails();
                $transfer->status = $result === ExtfJobResult::Succeeded ? DatevTransferStatus::Succeeded : DatevTransferStatus::Failed;
                $transfer->error = $result === ExtfJobResult::Failed
                    ? mb_substr(trim(($details?->getTitle() ?? '') . ' ' . ($details?->getDetail() ?? '')) ?: 'failed', 0, 300)
                    : null;
                $done++;
            }
            $transfer->save();
        }

        return $done;
    }
}
