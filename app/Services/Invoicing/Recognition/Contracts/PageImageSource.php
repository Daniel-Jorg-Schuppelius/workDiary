<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PageImageSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition\Contracts;

/** Seitenbilder eines PDF für den Zonen-Editor (Phase 151). */
interface PageImageSource {
    /** @return list<string> PNG-Pfade in Seitenreihenfolge, leer bei Fehler */
    public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array;
}
