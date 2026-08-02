<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalRecordPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Disposal;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Disposal\DisposalJob;
use App\Models\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Facades\Storage;

/**
 * Kundennachweis-PDF der Entsorgungsakte (Feature 100, MVP-475):
 * Übernahme-/Entsorgungsprotokoll mit Geräteliste, Datenträger-Behandlung,
 * Entsorger-/Nachweisbezug und Unterschrift auf dem Firmenbogen
 * (Dokumentart `Protocol` — Pflichtblöcke DocumentMeta + CompanyIdentity).
 * Der Inhalts-Hash im Footer macht den Nachweis prüffest (Muster
 * ProtocolHasher: kanonisches JSON, Schlüssel alphabetisch).
 */
class DisposalRecordPdfRenderer {
    public function render(DisposalJob $job): string {
        $job->loadMissing([
            'customer', 'site', 'responsible', 'creator',
            'items.treatments.performer', 'items.asset',
            'handovers.disposer', 'handovers.document',
            'signatureAttachment',
        ]);

        $organization = Organization::query()->withoutGlobalScopes()->find($job->organization_id);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Protocol,
            'pdf.disposal-record',
            [
                'job' => $job,
                'organization' => $organization,
                'hash' => $this->contentHash($job),
                'signatureDataUri' => $this->signatureDataUri($job),
                'generatedAt' => now(),
            ],
            $organization,
        );
    }

    public function filename(DisposalJob $job): string {
        return 'Entsorgungsnachweis_' . (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $job->number);
    }

    /** Reproduzierbarer Inhalts-Hash über den fachlichen Akteninhalt. */
    public function contentHash(DisposalJob $job): string {
        $job->loadMissing(['items.treatments', 'handovers']);

        $canonical = [
            'number' => $job->number,
            'customer_id' => $job->customer_id,
            'picked_up_on' => $job->picked_up_on?->toDateString(),
            'signer_name' => $job->signer_name,
            'signed_at' => $job->signed_at?->toIso8601String(),
            'signature_hash' => $job->signature_hash,
            'items' => $job->items->map(static fn (\App\Models\Disposal\DisposalItem $item): array => [
                'category' => $item->category,
                'manufacturer' => $item->manufacturer,
                'model' => $item->model,
                'serial_number' => $item->serial_number,
                'quantity' => $item->quantity,
                'weight_kg' => $item->weight_kg,
                'avv_code' => $item->avv_code,
                'is_hazardous' => $item->is_hazardous,
                'treatments' => $item->treatments->map(static fn (\App\Models\Disposal\DataMediaTreatment $treatment): array => [
                    'media_type' => $treatment->media_type->value,
                    'method' => $treatment->method->value,
                    'din_level' => $treatment->dinLevel(),
                    'protection_class' => $treatment->protection_class,
                    'treated_at' => $treatment->treated_at->toIso8601String(),
                    'performed_by' => $treatment->performed_by_user_id,
                    'evidence_reference' => $treatment->evidence_reference,
                ])->all(),
            ])->all(),
            'handovers' => $job->handovers->map(static fn (\App\Models\Disposal\DisposalHandover $handover): array => [
                'external_contact_id' => $handover->external_contact_id,
                'proof_type' => $handover->proof_type->value,
                'document_number' => $handover->document_number,
                'handed_over_on' => $handover->handed_over_on->toDateString(),
                'certificate_reference' => $handover->certificate_reference,
            ])->all(),
        ];

        return CryptoHelper::hash(JsonHelper::encode($this->sortKeysDeep($canonical)));
    }

    /** Unterschrifts-PNG als data-URI für die dompdf-Einbettung. */
    private function signatureDataUri(DisposalJob $job): ?string {
        $attachment = $job->signatureAttachment;
        if ($attachment === null) {
            return null;
        }

        $disk = Storage::disk($attachment->disk ?? 'local');
        if (!$disk->exists($attachment->path)) {
            return null;
        }

        $binary = $disk->get($attachment->path);
        if ($binary === null || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sortKeysDeep(array $data): array {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Listen behalten ihre Reihenfolge, nur String-Keys werden sortiert.
                $data[$key] = array_is_list($value)
                    ? array_map(fn ($entry) => is_array($entry) ? $this->sortKeysDeep($entry) : $entry, $value)
                    : $this->sortKeysDeep($value);
            }
        }

        return $data;
    }
}
