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
use App\Models\Platform\Organization;
use App\Services\DocumentDesign\{DocumentDesignRenderer, IssuerIdentity};

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

        $design = app(DocumentDesignRenderer::class);
        $organization = Organization::query()->withoutGlobalScopes()->find($enrollment->organization_id);
        $payload = $organization !== null ? $design->payloadFor($organization, RenderDocumentKind::Certificate) : null;

        return $design->renderPdf(
            RenderDocumentKind::Certificate,
            'learning.pdf.report-card',
            [
                'enrollment' => $enrollment,
                'result' => $this->gradebook->forEnrollment($enrollment),
                // Aussteller nach Blockzustand (MVP-1624); das Zeugnis ist ein Auszug zum Druckdatum, kein festgeschriebener Beleg.
                'design' => $design->context($payload),
                'issuer' => IssuerIdentity::for($organization),
            ],
            $organization,
            payload: $payload,
        );
    }

    /** Dateiname ohne Personendaten im Pfad. */
    public function filename(LearningEnrollment $enrollment): string {
        return 'zeugnis-' . str_replace(['/', '\\', ' '], '-', (string) ($enrollment->course->code ?? 'kurs')) . '-' . $enrollment->sqid . '.pdf';
    }
}
