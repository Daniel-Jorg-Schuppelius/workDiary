<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolkitPageImageSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Services\Invoicing\Recognition\Contracts\PageImageSource;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use PDFToolkit\Helper\PDFPageHelper;

/**
 * Seitenbilder aus dem pdf-toolkit (pdftoppm, MediaBox — die Zonen des Editors
 * rechnen in MediaBox-Koordinaten). Der Zonen-Editor rastert im Request: Seiten
 * ohne lesbare Maße oder mit übergroßer Media-/CropBox gehen nicht ans Raster
 * (Sicherheitsprüfung Feature 208, H1); die Pixelgrenze setzt zusätzlich das
 * Toolkit (RasterLimit).
 */
final class ToolkitPageImageSource implements PageImageSource {
    public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array {
        if (! IncomingInvoiceSplitter::pagesWithinLimits($pdfPath, $firstPage, $lastPage)) {
            return [];
        }

        return PDFPageHelper::renderPages($pdfPath, $outputDir, 'png', $dpi, $firstPage, $lastPage);
    }
}
