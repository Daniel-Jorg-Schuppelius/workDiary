<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GermanTaxNumberCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\ValueObjects\GermanTaxNumber;

/**
 * Steuernummer. Die kanonische Form wäre die reine Ziffernfolge; sie verliert
 * die Gliederung des Finanzamts (`151/815/08156`), die auf Belegen und in der
 * Kundenakte erwartet wird. Gültige Nummern verlieren deshalb nur ihre
 * Leerzeichen, Schrägstriche bleiben.
 *
 * @extends IdentifierCast<GermanTaxNumber>
 */
class GermanTaxNumberCast extends IdentifierCast {
    protected function toValueObject(string $raw): ?GermanTaxNumber {
        return GermanTaxNumber::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var GermanTaxNumber $value */
        return $value->getValue();
    }

    protected function normalise(string $raw): string {
        $compact = StringHelper::removeWhitespace($raw, unicode: true);

        return $compact !== '' && $this->toValueObject($compact) !== null ? $compact : $raw;
    }
}
