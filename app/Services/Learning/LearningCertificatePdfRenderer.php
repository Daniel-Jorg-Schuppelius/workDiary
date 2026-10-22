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
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use CommonToolkit\Helper\Data\DataUrlHelper;

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
        $verifyUrl = route('learning.certificates.verify', $certificate->verification_code);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Certificate,
            'learning.pdf.certificate',
            [
                'certificate' => $certificate,
                'verifyUrl' => $verifyUrl,
                // Prüfseite per Handy (MVP-1263) — dieselbe Adresse wie im Text darunter.
                'qrImage' => (string) DataUrlHelper::encode((new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd())))->writeString($verifyUrl), 'image/svg+xml'),
            ],
            (int) $certificate->organization_id,
        );
    }

    /** Dateiname für den Download — sprechend, ohne Personendaten im Pfad. */
    public function filename(LearningCertificate $certificate): string {
        return 'zertifikat-' . str_replace(['/', '\\', ' '], '-', $certificate->number) . '.pdf';
    }
}
