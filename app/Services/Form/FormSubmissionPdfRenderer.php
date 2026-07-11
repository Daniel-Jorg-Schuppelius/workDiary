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

use App\Models\FormSubmission;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Rendert ein ausgefülltes Formular als PDF (Feature 032, Rang 31) über die
 * pdf-toolkit `PDFWriterRegistry` — immer gegen den eingefrorenen
 * `fields_snapshot` der Submission (Versionssicherheit). Geteiltes Muster mit
 * {@see \App\Services\Invoicing\InvoicePdfRenderer}.
 */
class FormSubmissionPdfRenderer {
    public function output(FormSubmission $submission, ?string $subjectLabel = null): string {
        $submission->loadMissing(['template', 'submitter', 'subject', 'attachments']);

        $html = view('forms.submissions.pdf', [
            'submission' => $submission,
            'subjectLabel' => $subjectLabel,
        ])->render();

        // Feature 076: aktives Dokumentdesign anwenden (ohne Profil No-Op).
        $html = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->composeFor(
            \App\Models\Organization::query()->withoutGlobalScopes()->find($submission->organization_id),
            \App\Enums\DocumentDesign\RenderDocumentKind::Form,
            $html,
        );

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (forms.submissions.pdf).');
    }
}
