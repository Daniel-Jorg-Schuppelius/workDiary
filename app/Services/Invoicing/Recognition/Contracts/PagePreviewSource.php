<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PagePreviewSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition\Contracts;

use App\Services\Invoicing\Recognition\ToolkitPagePreviewSource;
use Illuminate\Container\Attributes\Bind;

/**
 * Vorschaubilder ganzer Seiten (Feature 208, Sammel-PDF): gerastert wird die
 * sichtbare Seite (CropBox), nicht die MediaBox — anders als die
 * Seitenbilder des Zonen-Editors ({@see PageImageSource}), deren Koordinaten
 * zu den Wortboxen passen müssen. Im Betrieb das pdf-toolkit, in Tests eine
 * Fälschung.
 */
#[Bind(ToolkitPagePreviewSource::class)]
interface PagePreviewSource {
    /** @return list<string> PNG-Pfade in Seitenreihenfolge, leer bei Fehler */
    public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array;
}
