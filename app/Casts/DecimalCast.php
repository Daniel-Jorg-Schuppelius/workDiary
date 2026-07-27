<?php
/*
 * Created on   : Sun Jul 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecimalCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\Model;

/**
 * Dimensionslose Dezimalspalte → {@see Decimal} (exakte bcmath-Arithmetik).
 *
 * Die Nachkommastellen der Spalte als Parameter mitgeben, damit gelesene und
 * gerechnete Werte dieselbe Skala haben: `DecimalCast::class . ':4'`.
 *
 * @extends ValueObjectCast<Decimal>
 */
class DecimalCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?Decimal {
        return Decimal::ofNullable($raw, $this->scaleOption());
    }

    protected function toStorage(object $value): string {
        /** @var Decimal $value */
        return $value->getValue();
    }
}
