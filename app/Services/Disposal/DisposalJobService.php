<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Disposal;

use App\Enums\Asset\AssetStatus;
use App\Enums\Disposal\{DisposalJobEventType, DisposalJobStatus};
use App\Enums\Document\DocumentType;
use App\Enums\Numbering\NumberScope;
use App\Models\{Attachment, Document, Organization, User};
use App\Models\Disposal\{DataMediaTreatment, DisposalHandover, DisposalItem, DisposalJob, DisposalJobEvent};
use App\Services\Asset\{AssetService, AssetStatusMachine};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Document\DocumentService;
use App\Services\Numbering\NumberSequenceService;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\ValueObjects\WasteCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lebenszyklus der Entsorgungsakte (Feature 100, MVP-474/475):
 * Anlage mit Nummernkreis, Geräteliste mit AVV-Gefährlichkeitsableitung
 * (WasteCode-VO, nie frei gesetzt), Datenträger-Behandlung, Entsorger-
 * Übergabe mit DMS-Beleg, Übernahme-Unterschrift und bewachter Abschluss:
 * erst wenn alle Gates erfüllt sind, entsteht der versionierte
 * Kundennachweis als freigegebenes DMS-Dokument (Portal-Ausgabe).
 */
class DisposalJobService {
    use AssertsStatusTransition;

    public const SIGNATURE_MAX_BYTES = 1_000_000; // 1 MB für Canvas-PNG

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly DocumentService $documents,
        private readonly AssetService $assets,
        private readonly AssetStatusMachine $assetStatusMachine,
        private readonly DisposalRecordPdfRenderer $recordRenderer,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function open(Organization $organization, User $creator, array $attributes): DisposalJob {
        return DB::transaction(function () use ($organization, $creator, $attributes): DisposalJob {
            $job = DisposalJob::query()->create(array_merge($attributes, [
                'organization_id' => $organization->id,
                'number' => $this->numbers->next($organization, NumberScope::Disposal),
                'status' => DisposalJobStatus::Draft->value,
                'created_by_user_id' => $creator->id,
            ]));

            $this->logEvent($job, DisposalJobEventType::Created, $creator, ['number' => $job->number]);

            return $job;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(DisposalJob $job, User $actor, array $attributes): DisposalJob {
        $this->assertEditable($job);

        $job->fill($attributes)->save();

        return $job->refresh();
    }

    /** @param array<string, mixed> $attributes */
    public function addItem(DisposalJob $job, User $actor, array $attributes): DisposalItem {
        $this->assertEditable($job);

        $code = $this->wasteCode((string) $attributes['avv_code']);

        return DB::transaction(function () use ($job, $actor, $attributes, $code): DisposalItem {
            /** @var DisposalItem $item */
            $item = $job->items()->create(array_merge($attributes, [
                'avv_code' => $code->getValue(),
                'is_hazardous' => $code->isHazardous(),
                'sort_order' => (int) $job->items()->max('sort_order') + 1,
            ]));

            $this->logEvent($job, DisposalJobEventType::ItemAdded, $actor, [
                'item_id' => $item->id,
                'category' => $item->category,
                'serial_number' => $item->serial_number,
                'avv_code' => $item->avv_code,
                'is_hazardous' => $item->is_hazardous,
            ]);

            return $item;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateItem(DisposalItem $item, User $actor, array $attributes): DisposalItem {
        $job = $item->job()->firstOrFail();
        $this->assertEditable($job);

        if (array_key_exists('avv_code', $attributes)) {
            $code = $this->wasteCode((string) $attributes['avv_code']);
            $attributes['avv_code'] = $code->getValue();
            $attributes['is_hazardous'] = $code->isHazardous();
        }

        return DB::transaction(function () use ($job, $item, $actor, $attributes): DisposalItem {
            $item->fill($attributes)->save();

            $this->logEvent($job, DisposalJobEventType::ItemUpdated, $actor, [
                'item_id' => $item->id,
                'avv_code' => $item->avv_code,
            ]);

            return $item->refresh();
        });
    }

    public function removeItem(DisposalItem $item, User $actor): void {
        $job = $item->job()->firstOrFail();
        $this->assertEditable($job);

        DB::transaction(function () use ($job, $item, $actor): void {
            $payload = [
                'item_id' => $item->id,
                'category' => $item->category,
                'serial_number' => $item->serial_number,
                'avv_code' => $item->avv_code,
            ];
            $item->delete();

            $this->logEvent($job, DisposalJobEventType::ItemRemoved, $actor, $payload);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addTreatment(DisposalItem $item, User $actor, array $attributes): DataMediaTreatment {
        $job = $item->job()->firstOrFail();
        $this->assertEditable($job);

        return DB::transaction(function () use ($job, $item, $actor, $attributes): DataMediaTreatment {
            /** @var DataMediaTreatment $treatment */
            $treatment = $item->treatments()->create(array_merge($attributes, [
                'performed_by_user_id' => $attributes['performed_by_user_id'] ?? $actor->id,
            ]));

            if (!$item->has_data_storage) {
                $item->forceFill(['has_data_storage' => true])->save();
            }

            $this->logEvent($job, DisposalJobEventType::TreatmentAdded, $actor, [
                'item_id' => $item->id,
                'treatment_id' => $treatment->id,
                'method' => $treatment->method->value,
                'din_level' => $treatment->dinLevel(),
            ]);

            return $treatment;
        });
    }

    public function removeTreatment(DataMediaTreatment $treatment, User $actor): void {
        $item = $treatment->item()->firstOrFail();
        $job = $item->job()->firstOrFail();
        $this->assertEditable($job);

        DB::transaction(function () use ($job, $item, $treatment, $actor): void {
            $payload = [
                'item_id' => $item->id,
                'treatment_id' => $treatment->id,
                'method' => $treatment->method->value,
                'din_level' => $treatment->dinLevel(),
            ];
            $treatment->delete();

            $this->logEvent($job, DisposalJobEventType::TreatmentRemoved, $actor, $payload);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addHandover(DisposalJob $job, User $actor, array $attributes, ?UploadedFile $proofFile = null): DisposalHandover {
        if (!in_array($job->status, [DisposalJobStatus::Collected, DisposalJobStatus::InTreatment, DisposalJobStatus::HandedOver], true)) {
            throw new RuntimeException((string) __('Entsorger-Übergaben können erst nach der Abholung erfasst werden.'));
        }

        return DB::transaction(function () use ($job, $actor, $attributes, $proofFile): DisposalHandover {
            if ($proofFile !== null) {
                $document = $this->documents->create($job, $actor, [
                    'title' => (string) __('Entsorgungsbeleg :number', ['number' => (string) ($attributes['document_number'] ?? $job->number)]),
                    'document_type' => DocumentType::Certificate->value,
                ], $proofFile);
                $attributes['document_id'] = $document->id;
            }

            /** @var DisposalHandover $handover */
            $handover = $job->handovers()->create(array_merge($attributes, [
                'created_by_user_id' => $actor->id,
            ]));

            $this->logEvent($job, DisposalJobEventType::HandoverAdded, $actor, [
                'handover_id' => $handover->id,
                'external_contact_id' => $handover->external_contact_id,
                'proof_type' => $handover->proof_type->value,
                'document_number' => $handover->document_number,
            ]);

            return $handover;
        });
    }

    public function removeHandover(DisposalHandover $handover, User $actor): void {
        $job = $handover->job()->firstOrFail();

        if ($job->status === DisposalJobStatus::Completed || $job->status === DisposalJobStatus::Cancelled) {
            throw new RuntimeException((string) __('Eine abgeschlossene oder stornierte Akte ist unveränderlich.'));
        }

        DB::transaction(function () use ($job, $handover, $actor): void {
            $payload = [
                'handover_id' => $handover->id,
                'proof_type' => $handover->proof_type->value,
                'document_number' => $handover->document_number,
            ];
            $handover->delete();

            $this->logEvent($job, DisposalJobEventType::HandoverRemoved, $actor, $payload);
        });
    }

    /** Statusübergang für die Zwischenzustände (Abholung, Behandlung, Übergabe). */
    public function transition(DisposalJob $job, User $actor, DisposalJobStatus $target, ?string $note = null): DisposalJob {
        if ($target === DisposalJobStatus::Completed || $target === DisposalJobStatus::Cancelled) {
            throw new RuntimeException((string) __('Abschluss und Storno laufen über die dafür vorgesehenen Aktionen.'));
        }

        $this->assertStatusTransition($job->status, $target);

        return DB::transaction(function () use ($job, $actor, $target, $note): DisposalJob {
            $from = $job->status;
            $job->forceFill(['status' => $target->value])->save();

            $this->logEvent($job, DisposalJobEventType::StatusChanged, $actor, array_filter([
                'from' => $from->value,
                'to' => $target->value,
                'note' => $note,
            ], static fn ($value): bool => $value !== null));

            return $job->refresh();
        });
    }

    /** Übernahme-Unterschrift des Kunden (Canvas-PNG, Muster Timesheet-Signatur). */
    public function sign(DisposalJob $job, User $actor, string $signerName, string $base64Png): DisposalJob {
        if (!$job->status->isOpen()) {
            throw new RuntimeException((string) __('Eine abgeschlossene oder stornierte Akte kann nicht mehr unterschrieben werden.'));
        }
        if ($job->isSigned()) {
            throw new RuntimeException((string) __('Die Übernahme ist bereits unterschrieben.'));
        }

        $binary = $this->decodePng($base64Png);
        if (strlen($binary) > self::SIGNATURE_MAX_BYTES) {
            throw new RuntimeException((string) __('Die Unterschrift ist zu groß.'));
        }

        return DB::transaction(function () use ($job, $actor, $signerName, $binary): DisposalJob {
            $folder = 'disposal/signatures/' . now()->format('Y/m');
            $path = $folder . '/' . Str::uuid()->toString() . '.png';
            Storage::disk('local')->put($path, $binary);

            /** @var Attachment $attachment */
            $attachment = $job->attachments()->create([
                'user_id' => $actor->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => 'signature.png',
                'mime' => 'image/png',
                'size' => strlen($binary),
            ]);

            $hash = CryptoHelper::hash($binary);

            $job->forceFill([
                'signer_name' => $signerName,
                'signed_at' => now(),
                'signature_attachment_id' => $attachment->id,
                'signature_hash' => $hash,
            ])->save();

            $this->logEvent($job, DisposalJobEventType::Signed, $actor, [
                'signer_name' => $signerName,
                'signature_hash' => $hash,
            ]);

            return $job->refresh();
        });
    }

    /**
     * Fachliche Abschluss-Gates (Feature 100 DoD) — leere Liste = abschließbar.
     * Wird auch von der Akten-Ansicht als Prüfpanel angezeigt.
     *
     * @return list<string>
     */
    public function completionBlockers(DisposalJob $job): array {
        $job->loadMissing(['items.treatments', 'handovers']);

        $blockers = [];

        if ($job->status !== DisposalJobStatus::HandedOver) {
            $blockers[] = (string) __('Die Akte muss an den Entsorger übergeben sein.');
        }

        if ($job->items->isEmpty()) {
            $blockers[] = (string) __('Die Akte enthält keine Geräteposition.');
        }

        if (!$job->isSigned()) {
            $blockers[] = (string) __('Die Übernahme-Unterschrift des Kunden fehlt.');
        }

        foreach ($job->items as $item) {
            if ($item->has_data_storage && $item->treatments->isEmpty()) {
                $blockers[] = (string) __('Datenträger-Behandlung fehlt für Position :position.', [
                    'position' => $item->category . ($item->serial_number !== null ? ' (' . $item->serial_number . ')' : ''),
                ]);
            }
        }

        $hasHazardous = $job->items->contains(fn (DisposalItem $item): bool => $item->is_hazardous);
        if ($hasHazardous && $job->handovers->isEmpty()) {
            $blockers[] = (string) __('Für gefährliche Abfälle (*-Schlüssel) fehlt der Entsorger-Nachweis.');
        }

        return $blockers;
    }

    /**
     * Abschluss: Gates prüfen, Status setzen, verknüpfte Assets ausmustern,
     * Kundennachweis erzeugen und im Portal freigeben.
     */
    public function complete(DisposalJob $job, User $actor): DisposalJob {
        $this->assertStatusTransition($job->status, DisposalJobStatus::Completed);

        $blockers = $this->completionBlockers($job);
        if ($blockers !== []) {
            throw new RuntimeException(implode(' ', $blockers));
        }

        DB::transaction(function () use ($job, $actor): void {
            $job->forceFill([
                'status' => DisposalJobStatus::Completed->value,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ])->save();

            foreach ($job->items as $item) {
                $asset = $item->asset;
                if ($asset === null || $asset->status === AssetStatus::Decommissioned) {
                    continue;
                }
                if (!$this->assetStatusMachine->canTransition($asset->status, AssetStatus::Decommissioned)) {
                    continue; // Endzustände respektieren, Abschluss nicht blockieren
                }
                $this->assets->decommission($asset, $actor, now()->toDateString());
            }

            $this->logEvent($job, DisposalJobEventType::Completed, $actor, [
                'completed_at' => now()->toIso8601String(),
            ]);
        });

        // MVP-650: Designstand VOR dem Nachweis-Rendern einfrieren — der
        // Kundennachweis entsteht bereits mit dem eingefrorenen Profil.
        $job->refresh();
        if ($job->organization !== null) {
            app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->snapshot(
                $job,
                \App\Enums\DocumentDesign\RenderDocumentKind::Protocol,
                $job->organization,
                user: $actor,
            );
        }

        $this->renderRecord($job, $actor);

        return $job->refresh();
    }

    /**
     * Kundennachweis-PDF erzeugen bzw. neu erzeugen: Erst-Erzeugung legt das
     * DMS-Dokument an und gibt es für den Kunden frei, jede weitere Erzeugung
     * wird eine neue Dokument-Version (versionierter Nachweis).
     */
    public function renderRecord(DisposalJob $job, User $actor): Document {
        $bytes = $this->recordRenderer->render($job);
        $hash = $this->recordRenderer->contentHash($job);
        $filename = $this->recordRenderer->filename($job) . '.pdf';

        $document = $job->recordDocument()->first();

        if ($document === null) {
            $document = $this->documents->createFromContents($job, $actor, [
                'title' => (string) __('Entsorgungsnachweis :number', ['number' => $job->number]),
                'document_type' => DocumentType::Certificate->value,
            ], $bytes, $filename, 'application/pdf');

            $job->forceFill(['record_document_id' => $document->id])->save();

            if ($document->isReleasableToCustomer()) {
                $this->documents->releaseToCustomer($document, $actor);
            }
        } else {
            $this->documents->addVersionFromContents($document, $actor, $bytes, $filename, 'application/pdf', null, 'generated');
        }

        $this->logEvent($job, DisposalJobEventType::RecordRendered, $actor, [
            'document_id' => $document->id,
            'content_hash' => $hash,
        ]);

        return $document;
    }

    public function cancel(DisposalJob $job, User $actor, string $reason): DisposalJob {
        $this->assertStatusTransition($job->status, DisposalJobStatus::Cancelled);

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException((string) __('Ein Storno braucht eine Begründung.'));
        }

        return DB::transaction(function () use ($job, $actor, $reason): DisposalJob {
            $job->forceFill([
                'status' => DisposalJobStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            $this->logEvent($job, DisposalJobEventType::Cancelled, $actor, ['reason' => $reason]);

            return $job->refresh();
        });
    }

    private function assertEditable(DisposalJob $job): void {
        if (!$job->status->isEditable()) {
            throw new RuntimeException((string) __('Die Akte ist im Status :status nicht mehr änderbar.', [
                'status' => $job->status->label(),
            ]));
        }
    }

    /** AVV-Schlüssel validieren/normalisieren — Gefährlichkeit nur aus dem Stern. */
    private function wasteCode(string $input): WasteCode {
        $code = WasteCode::tryFrom($input);
        if ($code === null) {
            throw new RuntimeException((string) __('Ungültiger AVV-Abfallschlüssel: :code', ['code' => $input]));
        }

        return $code;
    }

    private function decodePng(string $payload): string {
        $payload = trim($payload);
        if (str_starts_with($payload, 'data:image/png;base64,')) {
            $payload = substr($payload, strlen('data:image/png;base64,'));
        }
        $binary = base64_decode($payload, true);
        if ($binary === false || strlen($binary) < 8 || substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new RuntimeException((string) __('Die Unterschrift ist kein gültiges PNG.'));
        }

        return $binary;
    }

    /** @param array<string, mixed> $payload */
    private function logEvent(DisposalJob $job, DisposalJobEventType $event, User $actor, array $payload = []): void {
        DisposalJobEvent::query()->create([
            'disposal_job_id' => $job->id,
            'event' => $event->value,
            'actor_user_id' => $actor->id,
            'payload' => $payload !== [] ? $payload : null,
            'created_at' => now(),
        ]);
    }
}
