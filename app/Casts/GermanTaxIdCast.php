<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GermanTaxIdCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use CommonToolkit\ValueObjects\GermanTaxId;

/**
 * Steuerliche Identifikationsnummer (11-stellig, mit Prüfziffer), gespeichert
 * als Ziffernfolge. In der Personalakte verschlüsselt abgelegt.
 *
 * @extends IdentifierCast<GermanTaxId>
 */
class GermanTaxIdCast extends IdentifierCast {
    protected function toValueObject(string $raw): ?GermanTaxId {
        return GermanTaxId::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var GermanTaxId $value */
        return $value->getValue();
    }
}
