<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradeCertificatePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Club\ClubMemberGrade;
use App\Models\Platform\Organization;
use App\Services\DocumentDesign\{DocumentDesignRenderer, IssuerIdentity};

/** Graduierungsbescheinigung als PDF (MVP-847) über den vorhandenen Dokument-Renderer; ein Widerruf wird sichtbar gedruckt. */
class ClubGradeCertificatePdfRenderer {
    public function output(ClubMemberGrade $memberGrade): string {
        $memberGrade->loadMissing(['member', 'grade', 'system', 'confirmedBy']);
        $candidate = $memberGrade->club_exam_candidate_id !== null
            ? \App\Models\Club\ClubExamCandidate::query()->whereKey($memberGrade->club_exam_candidate_id)->with(['offer.event', 'resultBy'])->first()
            : null;

        $design = app(DocumentDesignRenderer::class);
        $organization = Organization::query()->withoutGlobalScopes()->find($memberGrade->organization_id);
        $payload = $design->payloadFromSnapshot($memberGrade, RenderDocumentKind::Certificate)
            ?? ($organization !== null ? $design->payloadFor($organization, RenderDocumentKind::Certificate) : null);

        return $design->renderPdf(
            RenderDocumentKind::Certificate,
            'club.pdf.grade_certificate',
            [
                'memberGrade' => $memberGrade,
                'candidate' => $candidate,
                // Aussteller nach Blockzustand (MVP-1624); vorher verliehene Grade bleiben, wie sie waren.
                'design' => $design->contextForDocument($memberGrade, RenderDocumentKind::Certificate, $payload, finalized: true),
                'issuer' => IssuerIdentity::for($organization),
            ],
            $organization,
            payload: $payload,
        );
    }

    /** Sprechender Dateiname ohne Personendaten. */
    public function filename(ClubMemberGrade $memberGrade): string {
        return 'graduierung-' . $memberGrade->sqid . '.pdf';
    }
}
