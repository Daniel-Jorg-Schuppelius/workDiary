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
use App\Models\FormSubmission;
use App\Services\DocumentDesign\DocumentDesignRenderer;

/**
 * Rendert ein ausgefülltes Formular als PDF (Feature 032, Rang 31) über die
 * pdf-toolkit `PDFWriterRegistry` — immer gegen den eingefrorenen
 * `fields_snapshot` der Submission (Versionssicherheit). Geteiltes Muster mit
 * {@see \App\Services\Invoicing\InvoicePdfRenderer}.
 */
class FormSubmissionPdfRenderer {
    public function output(FormSubmission $submission, ?string $subjectLabel = null): string {
        $submission->loadMissing(['template', 'submitter', 'subject', 'attachments']);

        // C15: gemeinsamer View→Design→PDF-Dreischritt (Dokumentdesign ohne Profil No-Op).
        // MVP-650: Einreichungen rendern mit dem Designstand des Einreichzeitpunkts.
        $design = app(DocumentDesignRenderer::class);

        return $design->renderPdf(
            RenderDocumentKind::Form,
            'forms.submissions.pdf',
            [
                'submission' => $submission,
                'subjectLabel' => $subjectLabel,
            ],
            (int) $submission->organization_id,
            payload: $design->payloadFromSnapshot($submission, RenderDocumentKind::Form),
        );
    }
}
