<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssuedDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering\Dto;

/** Ergebnis einer Übertragung: Beleg im Programm und die dort vergebene Nummer. */
final readonly class IssuedDocument {
    public function __construct(
        public string $externalId,
        public string $number,
    ) {}
}
