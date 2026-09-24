<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldSchemaCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use App\Services\Fields\FieldSchema;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON-Spalte ↔ {@see FieldSchema} (MVP-868): gespeichert wird die Kanonik
 * `FieldSchema::toArray()`.
 *
 * @implements CastsAttributes<FieldSchema, FieldSchema|array<mixed>|null>
 */
class FieldSchemaCast implements CastsAttributes {
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): FieldSchema {
        if ($value === null || $value === '') {
            return new FieldSchema([]);
        }

        return FieldSchema::fromArray(is_string($value) ? JsonHelper::decode($value, true) : $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        $schema = $value instanceof FieldSchema ? $value : FieldSchema::fromArray($value);

        return [$key => JsonHelper::encode($schema->toArray())];
    }
}
