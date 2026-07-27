<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GermanTaxNumberCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\GermanTaxNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Steuernummer → {@see GermanTaxNumber}.
 *
 * @extends ValueObjectCast<GermanTaxNumber>
 */
class GermanTaxNumberCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?GermanTaxNumber {
        return GermanTaxNumber::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var GermanTaxNumber $value */
        return $value->getValue();
    }
}
