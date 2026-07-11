<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timesheet;

use App\Models\Timesheet;
use Illuminate\Support\Facades\{Storage, View};
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

class PdfRenderer {
    public function render(Timesheet $timesheet): string {
        $timesheet->loadMissing(['project', 'user', 'entries.task', 'materialUsages.material', 'signatureAttachment']);

        $signaturePng = null;
        if ($timesheet->signatureAttachment) {
            $att = $timesheet->signatureAttachment;
            if (Storage::disk($att->disk)->exists($att->path)) {
                $signaturePng = 'data:image/png;base64,' . base64_encode(
                    Storage::disk($att->disk)->get($att->path) ?? ''
                );
            }
        }

        $html = View::make('pdf.timesheet', [
            'timesheet' => $timesheet,
            'signaturePng' => $signaturePng,
        ])->render();

        // Feature 076: aktives Dokumentdesign anwenden (ohne Profil No-Op).
        $html = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->composeFor(
            \App\Models\Organization::query()->withoutGlobalScopes()->find($timesheet->organization_id),
            \App\Enums\DocumentDesign\RenderDocumentKind::Timesheet,
            $html,
        );

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (pdf.timesheet).');
    }

    public function store(Timesheet $timesheet): string {
        $bytes = $this->render($timesheet);
        $path = sprintf('timesheets/pdf/%d.pdf', $timesheet->id);
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
