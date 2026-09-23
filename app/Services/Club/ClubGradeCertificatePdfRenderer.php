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
use App\Services\DocumentDesign\DocumentDesignRenderer;

/** Graduierungsbescheinigung als PDF (MVP-847) über den vorhandenen Dokument-Renderer; ein Widerruf wird sichtbar gedruckt. */
class ClubGradeCertificatePdfRenderer {
    public function output(ClubMemberGrade $memberGrade): string {
        $memberGrade->loadMissing(['member', 'grade', 'system', 'confirmedBy']);
        $candidate = $memberGrade->club_exam_candidate_id !== null
            ? \App\Models\Club\ClubExamCandidate::query()->whereKey($memberGrade->club_exam_candidate_id)->with(['offer.event', 'resultBy'])->first()
            : null;

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Certificate,
            'club.pdf.grade_certificate',
            ['memberGrade' => $memberGrade, 'candidate' => $candidate],
            (int) $memberGrade->organization_id,
        );
    }

    /** Sprechender Dateiname ohne Personendaten. */
    public function filename(ClubMemberGrade $memberGrade): string {
        return 'graduierung-' . $memberGrade->sqid . '.pdf';
    }
}
