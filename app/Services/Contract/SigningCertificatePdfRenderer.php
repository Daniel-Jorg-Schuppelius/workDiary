<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SigningCertificatePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Contract\ContractSigningRevision;
use App\Services\BrandingService;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Support\DocumentLocale;

/**
 * Abschlussnachweis einer unterzeichneten Kundenvereinbarung (Feature 157):
 * Parteien, Fassungskennung, Dateiliste mit Hashes, Signaturmethoden und
 * Zeitpunkte — nachträglich gerendert, also keine kryptografische
 * PDF-Signatur. Läuft über die Design-Pipeline (Art `signing_certificate`).
 */
class SigningCertificatePdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    public function output(ContractSigningRevision $revision): string {
        $revision->loadMissing(['contract.customer', 'contract.organization', 'manifestItems', 'requests.evidences', 'evidences']);
        $contract = $revision->contract;

        return DocumentLocale::within($contract?->customer, $contract?->organization, fn (): string => $this->design->renderPdf(
            RenderDocumentKind::SigningCertificate,
            'contracts.signing.certificate-pdf',
            $this->viewData($revision),
            $contract?->organization,
            payload: $contract?->organization === null ? null : $this->design->payloadFor($contract->organization, RenderDocumentKind::SigningCertificate, (int) $contract->customer_id),
        ));
    }

    /** @return array<string, mixed> */
    public function viewData(ContractSigningRevision $revision): array {
        $contract = $revision->contract;

        return [
            'revision' => $revision,
            'contract' => $contract,
            'items' => $revision->manifestItems,
            'requests' => $revision->requests,
            'orgLegal' => app(BrandingService::class)->legalFor($contract?->organization),
            'design' => $this->design->context($contract?->organization === null ? null : $this->design->payloadFor($contract->organization, RenderDocumentKind::SigningCertificate, (int) $contract->customer_id)),
        ];
    }
}
