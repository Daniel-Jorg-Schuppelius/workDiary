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

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Timesheet;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Illuminate\Support\Facades\Storage;

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

        // C15: gemeinsamer View→Design→PDF-Dreischritt (Dokumentdesign ohne Profil No-Op).
        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Timesheet,
            'pdf.timesheet',
            [
                'timesheet' => $timesheet,
                'signaturePng' => $signaturePng,
            ],
            (int) $timesheet->organization_id,
        );
    }

    public function store(Timesheet $timesheet): string {
        $bytes = $this->render($timesheet);
        $path = sprintf('timesheets/pdf/%d.pdf', $timesheet->id);
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
