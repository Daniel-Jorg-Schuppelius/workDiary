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

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gemeinsames DomPDF-Boilerplate der Report-Controller: View rendern,
 * A4-Papier setzen, als Download ausliefern. View-Name, Datenaufbereitung
 * und Orientierung bleiben Sache des jeweiligen Controllers.
 */
trait RendersReportPdf {
    /**
     * @param  array<string, mixed>  $data
     */
    protected function pdfDownload(string $view, array $data, string $filename, string $orientation = 'portrait'): SymfonyResponse {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', $orientation);

        return $pdf->download($filename);
    }
}
