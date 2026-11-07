<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceFile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Dto;

/** Original einer extern ausgestellten Rechnung, unverändert wie geliefert (Feature 196). */
final readonly class ExternalInvoiceFile {
    public function __construct(
        public string $content,
        public string $mime,
        public string $filename,
    ) {}
}
