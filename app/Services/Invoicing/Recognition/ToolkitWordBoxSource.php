<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolkitWordBoxSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Services\Invoicing\Recognition\Contracts\WordBoxSource;
use Illuminate\Support\Facades\Log;
use PDFToolkit\Helper\PDFTextProvider;
use Throwable;

/** Wortboxen aus dem pdf-toolkit (0.27): Textlayer, bei Scans OCR. */
final class ToolkitWordBoxSource implements WordBoxSource {
    public function boxesFor(string $pdfPath): array {
        try {
            return (new PDFTextProvider($pdfPath))->wordBoxes();
        } catch (Throwable $e) {
            Log::info('Wortboxen nicht lesbar', ['path' => basename($pdfPath), 'error' => $e->getMessage()]);

            return [];
        }
    }
}
