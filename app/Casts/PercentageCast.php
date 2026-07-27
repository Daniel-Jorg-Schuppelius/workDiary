<?php
/*
 * Created on   : Sun Jul 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PercentageCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\Model;

/**
 * Prozentspalte (Steuersatz, Rabatt) → {@see Percentage}.
 *
 * Gespeichert wird weiter der reine Zahlwert (`19.00`), nicht `19 %` — die
 * Spalte bleibt `decimal` und damit in SQL summier- und gruppierbar. Rechnen
 * über `amountOf()`/`addTo()`, die direkt {@see \CommonToolkit\ValueObjects\Money}
 * zurückgeben.
 *
 * @extends ValueObjectCast<Percentage>
 */
class PercentageCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?Percentage {
        return Percentage::tryFrom($raw, $this->scaleOption());
    }

    protected function toStorage(object $value): string {
        /** @var Percentage $value */
        return $value->getNumericValue();
    }
}
