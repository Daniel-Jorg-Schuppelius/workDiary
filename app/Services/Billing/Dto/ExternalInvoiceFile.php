<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceFile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

/**
 * Belegdatei aus dem Buchhaltungsprogramm (MVP-1118). `contentType` ist schon
 * gegen die Positivliste der Quelle geprüft.
 */
final readonly class ExternalInvoiceFile {
    public function __construct(
        public string $body,
        public string $contentType,
        public string $filename,
    ) {}
}
