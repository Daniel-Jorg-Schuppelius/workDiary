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
use PDFToolkit\Helper\PDFPageHelper;

/** Seitenbilder aus dem pdf-toolkit (pdftoppm). */
final class ToolkitPageImageSource implements PageImageSource {
    public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array {
        return PDFPageHelper::renderPages($pdfPath, $outputDir, 'png', $dpi, $firstPage, $lastPage);
    }
}
