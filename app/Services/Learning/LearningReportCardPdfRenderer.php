<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningReportCardPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Learning\LearningEnrollment;
use App\Services\DocumentDesign\DocumentDesignRenderer;

/**
 * Zeugnis je Einschreibung (Feature 149, MVP-790): die Bestandteile des
 * Notenbuchs als Leistungsnachweis — über das Dokumentdesign wie das
 * Zertifikat (Briefkopf, Fußzeile der Organisation). Ein offenes Notenbuch
 * wird trotzdem gerendert, mit sichtbarem Vermerk „vorläufig".
 */
class LearningReportCardPdfRenderer {
    public function __construct(private readonly LearningGradebookService $gradebook) {}

    public function output(LearningEnrollment $enrollment): string {
        $enrollment->loadMissing(['course', 'user', 'externalParticipant']);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Certificate,
            'learning.pdf.report-card',
            [
                'enrollment' => $enrollment,
                'result' => $this->gradebook->forEnrollment($enrollment),
            ],
            (int) $enrollment->organization_id,
        );
    }

    /** Dateiname ohne Personendaten im Pfad. */
    public function filename(LearningEnrollment $enrollment): string {
        return 'zeugnis-' . str_replace(['/', '\\', ' '], '-', (string) ($enrollment->course->code ?? 'kurs')) . '-' . $enrollment->sqid . '.pdf';
    }
}
