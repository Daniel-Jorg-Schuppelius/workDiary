<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolkitPagePreviewSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Services\Invoicing\Recognition\Contracts\PagePreviewSource;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use PDFToolkit\Helper\PDFPageHelper;

/**
 * Vorschau über pdftoppm mit CropBox. Seiten ohne lesbare Maße oder mit
 * übergroßer Media-/CropBox gehen nicht ans Raster (Feature 208, H1); die
 * Pixelgrenze setzt zusätzlich das Toolkit (RasterLimit).
 */
final class ToolkitPagePreviewSource implements PagePreviewSource {
    public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array {
        if (! IncomingInvoiceSplitter::pagesWithinLimits($pdfPath, $firstPage, $lastPage)) {
            return [];
        }

        return PDFPageHelper::renderPages($pdfPath, $outputDir, 'png', $dpi, $firstPage, $lastPage, cropBox: true);
    }
}
