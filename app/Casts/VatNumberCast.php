<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatNumberCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\VatNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * USt-IdNr. → {@see VatNumber} (Länderpräfix + nationale Nummer).
 *
 * @extends ValueObjectCast<VatNumber>
 */
class VatNumberCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?VatNumber {
        return VatNumber::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var VatNumber $value */
        return $value->getValue();
    }
}
