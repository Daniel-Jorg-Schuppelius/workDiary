<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuantityCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\Quantity;
use Illuminate\Database\Eloquent\Model;

/**
 * Mengenspalte + Einheitenspalte → {@see Quantity}.
 *
 * Die Einheit kommt aus einer Schwesterspalte, wahlweise über einen
 * Relationspfad (nur bei geladener Relation, sonst N+1):
 * `'quantity' => QuantityCast::class . ':unit,4'`.
 *
 * Nur für Datensätze mit eigener Einheit. Bestände, Reservierungen und
 * Fertigungsmengen führen ihre Menge in der Basiseinheit der Variante — dort
 * gäbe es keine Einheit zu binden, sie bleiben `decimal`.
 *
 * @extends ValueObjectCast<Quantity>
 */
class QuantityCast extends ValueObjectCast {
    /** Einheit für Altbestand ohne gesetzte Einheit — {@see Quantity} lässt keine leere zu. */
    private const FALLBACK_UNIT = 'Stk';

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?Quantity {
        return Quantity::tryFrom($raw, $this->unit($model, $attributes), $this->scaleOption());
    }

    protected function toStorage(object $value): string {
        /** @var Quantity $value */
        return $value->getNumericValue();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function unit(Model $model, array $attributes): string {
        foreach ($this->options as $option) {
            if (ctype_digit($option)) {
                continue;
            }

            $raw = str_contains($option, '.')
                ? $this->fromRelation($model, $option)
                : ($attributes[$option] ?? $model->getAttribute($option));

            if (is_string($raw) && trim($raw) !== '') {
                return trim($raw);
            }
        }

        return self::FALLBACK_UNIT;
    }

    /** Einheit aus einer geladenen Relation („order.unit“); sonst null. */
    private function fromRelation(Model $model, string $path): mixed {
        [$relation, $column] = explode('.', $path, 2);

        if (!$model->relationLoaded($relation)) {
            return null;
        }

        $related = $model->getRelation($relation);

        return $related instanceof Model ? $related->getAttribute($column) : null;
    }
}
