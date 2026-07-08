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

use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gemeinsames PDF-Boilerplate der Report-Controller (pdf-toolkit
 * `PDFWriterRegistry`): View rendern, A4-Papier setzen, als Download
 * ausliefern. View-Name, Datenaufbereitung und Orientierung bleiben Sache
 * des jeweiligen Controllers.
 */
trait RendersReportPdf {
    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function pdfDownload(string $view, array $data, string $filename, string $orientation = 'portrait'): SymfonyResponse {
        $html = view($view, $data)->render();
        $bytes = PDFWriterRegistry::getInstance()->createPdfString(
            PDFContent::fromHtml($html),
            ['orientation' => $orientation],
        ) ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (' . $view . ').');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
