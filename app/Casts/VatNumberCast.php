<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatNumberCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use CommonToolkit\ValueObjects\VatNumber;

/**
 * USt-IdNr.: gespeichert als Länderpräfix + nationale Nummer ohne Leerzeichen
 * (`DE811907980`), gelesen Text.
 *
 * @extends IdentifierCast<VatNumber>
 */
class VatNumberCast extends IdentifierCast {
    protected function toValueObject(string $raw): ?VatNumber {
        return VatNumber::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var VatNumber $value */
        return $value->getValue();
    }
}
