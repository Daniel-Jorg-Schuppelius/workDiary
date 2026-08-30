<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCertificatePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Learning\LearningCertificate;
use App\Services\DocumentDesign\DocumentDesignRenderer;

/**
 * Zertifikat als PDF (Feature 149, MVP-740) über die Design-Pipeline —
 * gleiches Muster wie {@see \App\Services\Form\FormSubmissionPdfRenderer}.
 *
 * **Der Ausdruck ist eine Kopie, nicht der Nachweis.** Maßgeblich bleibt der
 * Datensatz mit seinem Prüfcode; deshalb trägt jedes Blatt die Prüfadresse.
 * Ein widerrufenes Zertifikat wird trotzdem gerendert — mit sichtbarem
 * Widerrufsvermerk, weil ein spurloses Verschwinden den Widerruf verschleiern
 * würde.
 */
class LearningCertificatePdfRenderer {
    public function output(LearningCertificate $certificate): string {
        $certificate->loadMissing(['course', 'enrollment.course']);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Certificate,
            'learning.pdf.certificate',
            [
                'certificate' => $certificate,
                'verifyUrl' => route('learning.certificates.verify', $certificate->verification_code),
            ],
            (int) $certificate->organization_id,
        );
    }

    /** Dateiname für den Download — sprechend, ohne Personendaten im Pfad. */
    public function filename(LearningCertificate $certificate): string {
        return 'zertifikat-' . str_replace(['/', '\\', ' '], '-', $certificate->number) . '.pdf';
    }
}
