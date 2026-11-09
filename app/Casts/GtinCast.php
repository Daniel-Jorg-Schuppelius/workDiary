<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GtinCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use CommonToolkit\ValueObjects\Gtin;

/**
 * GTIN/EAN inkl. Prüfziffer, gespeichert als Ziffernfolge.
 *
 * @extends IdentifierCast<Gtin>
 */
class GtinCast extends IdentifierCast {
    protected function toValueObject(string $raw): ?Gtin {
        return Gtin::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var Gtin $value */
        return $value->getValue();
    }
}
