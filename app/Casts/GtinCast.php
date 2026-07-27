<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GtinCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\Gtin;
use Illuminate\Database\Eloquent\Model;

/**
 * GTIN/EAN → {@see Gtin} inkl. Prüfziffer. Ungültige Altbestände ergeben
 * beim Lesen `null` statt einer Exception.
 *
 * @extends ValueObjectCast<Gtin>
 */
class GtinCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?Gtin {
        return Gtin::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var Gtin $value */
        return $value->getValue();
    }
}
