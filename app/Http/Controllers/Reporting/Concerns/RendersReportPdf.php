<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RendersReportPdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gemeinsames PDF-Boilerplate der Report-Controller: View→Design→PDF über
 * {@see DocumentDesignRenderer::renderPdf} (C15), Auslieferung als Download.
 * View-Name, Datenaufbereitung und Orientierung bleiben Sache des jeweiligen
 * Controllers.
 *
 * A9: Mit $request+$reportCode wird `report.exported` direkt hier
 * geschrieben (auditExport kommt aus WritesReportCsv, s. abstract).
 */
trait RendersReportPdf {
    /**
     * Audit-Schreiber — konkret in WritesReportCsv.
     *
     * @param  array<string, mixed>  $filters
     */
    abstract protected function auditExport(Request $request, string $reportCode, string $format, array $filters): void;

    /**
     * @param  string  $view
     * @phpstan-param  view-string  $view
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     */
    protected function pdfDownload(string $view, array $data, string $filename, string $orientation = 'portrait', ?Request $request = null, ?string $reportCode = null, array $filters = []): SymfonyResponse {
        if ($request !== null && $reportCode !== null) {
            $this->auditExport($request, $reportCode, 'pdf', $filters);
        }

        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $bytes = app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            $view,
            $data,
            $org instanceof Organization ? $org : null,
            ['orientation' => $orientation],
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
