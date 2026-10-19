<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WordBoxSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition\Contracts;

use PDFToolkit\Entities\WordBox;

/** Wortboxen eines PDF (Phase 151): Toolkit in Betrieb, Fälschung in Tests. */
interface WordBoxSource {
    /** @return list<WordBox> */
    public function boxesFor(string $pdfPath): array;
}
