<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldValuesCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use App\Services\Fields\FieldValues;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON-Spalte ↔ {@see FieldValues} (MVP-868).
 *
 * @implements CastsAttributes<FieldValues, FieldValues|array<string, mixed>|null>
 */
class FieldValuesCast implements CastsAttributes {
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): FieldValues {
        if ($value === null || $value === '') {
            return new FieldValues;
        }

        return FieldValues::fromArray(is_string($value) ? JsonHelper::decode($value, true) : $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        $values = $value instanceof FieldValues ? $value : FieldValues::fromArray($value);

        return [$key => JsonHelper::encode($values->toArray())];
    }
}
