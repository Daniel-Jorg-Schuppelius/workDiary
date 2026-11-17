<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Form;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Form\FormSubmission;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\FileSystem\File;
use PDFToolkit\Helper\PDFHelper;

/**
 * Rendert ein ausgefülltes Formular als PDF (Feature 032, Rang 31) über die
 * pdf-toolkit `PDFWriterRegistry` — immer gegen den eingefrorenen
 * `fields_snapshot` der Submission (Versionssicherheit). Geteiltes Muster mit
 * {@see \App\Services\Invoicing\InvoicePdfRenderer}.
 *
 * Papierlayout (Feature 203, MVP-1651): Die Fußzeile nennt „Seite x von y“.
 * dompdf kennt die Gesamtzahl erst nach dem Rendern; deshalb zählt das
 * pdf-toolkit die Seiten des ersten Laufs (pdfinfo) und ein zweiter Lauf
 * setzt die Zahl ein. Fehlt pdfinfo, bleibt es bei „Seite x“.
 */
class FormSubmissionPdfRenderer {
    public function output(FormSubmission $submission, ?string $subjectLabel = null): string {
        $submission->loadMissing(['template', 'submitter', 'subject', 'attachments']);

        // C15: gemeinsamer View→Design→PDF-Dreischritt (Dokumentdesign ohne Profil No-Op).
        // MVP-650: Einreichungen rendern mit dem Designstand des Einreichzeitpunkts.
        $design = app(DocumentDesignRenderer::class);
        $payload = $design->payloadFromSnapshot($submission, RenderDocumentKind::Form);
        $render = static fn (?int $pages): string => $design->renderPdf(
            RenderDocumentKind::Form,
            'forms.submissions.pdf',
            [
                'submission' => $submission,
                'subjectLabel' => $subjectLabel,
                'pages' => $pages,
            ],
            (int) $submission->organization_id,
            payload: $payload,
        );

        $first = $render(null);
        $pages = File::withTemp($first, static fn (string $path): int => PDFHelper::getPageCount($path), 'form-pdf-', 'pdf');

        return $pages > 0 ? $render($pages) : $first;
    }
}
