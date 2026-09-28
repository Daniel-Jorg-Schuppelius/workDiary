<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEvidencePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Safety;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Platform\Organization;
use App\Models\Safety\{HazardAssessment, SafetyInstruction, SafetyInstructionParticipant};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\Data\DataUrlHelper;
use Illuminate\Support\Facades\Storage;

/**
 * Nachweise des Arbeitsschutz-Registers als PDF (MVP-985): Unterweisungs-
 * nachweis mit Teilnehmenden, Zeitpunkt, Nachweisform, Prüfwert und
 * gezeichneter Unterschrift sowie die Gefährdungsbeurteilung mit Positionen
 * und Risiko vor/nach Maßnahme — auf dem Firmenbogen (Dokumentart `Protocol`).
 */
class SafetyEvidencePdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    public function instruction(SafetyInstruction $instruction): string {
        $instruction->loadMissing(['participants.user:id,name', 'instructor:id,name', 'assessment']);
        $organization = Organization::query()->withoutGlobalScopes()->find($instruction->organization_id);

        return $this->design->renderPdf(RenderDocumentKind::Protocol, 'pdf.safety-instruction', [
            'instruction' => $instruction,
            'organization' => $organization,
            'signatures' => $instruction->participants->mapWithKeys(fn (SafetyInstructionParticipant $participant): array => [$participant->id => $this->signatureDataUri($participant)])->all(),
            'generatedAt' => now(),
        ], $organization);
    }

    public function assessment(HazardAssessment $assessment): string {
        $assessment->loadMissing(['items', 'approvedBy:id,name', 'createdBy:id,name', 'supersedes']);
        $organization = Organization::query()->withoutGlobalScopes()->find($assessment->organization_id);

        return $this->design->renderPdf(RenderDocumentKind::Protocol, 'pdf.safety-assessment', [
            'assessment' => $assessment,
            'organization' => $organization,
            'generatedAt' => now(),
        ], $organization, ['orientation' => 'landscape']);
    }

    public function filename(string $prefix, string $displayNo): string {
        return $prefix . '_' . (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $displayNo) . '.pdf';
    }

    private function signatureDataUri(SafetyInstructionParticipant $participant): ?string {
        $path = $participant->signature_image_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('local')->get($path);

        return $binary === null || $binary === '' ? null : (DataUrlHelper::encode($binary, 'image/png') ?: null);
    }
}
