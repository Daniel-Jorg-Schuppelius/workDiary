<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldDocumentCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use App\Services\Fields\FieldDocument;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON-Spalte ↔ {@see FieldDocument} (MVP-866): liest Altbestand in freier
 * Form, schreibt die Kanonik `{schema, values}`. Leere Dokumente werden null.
 *
 * @implements CastsAttributes<FieldDocument, FieldDocument|array<mixed>|null>
 */
class FieldDocumentCast implements CastsAttributes {
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?FieldDocument {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = is_string($value) ? JsonHelper::decode($value, true) : $value;

        return FieldDocument::fromStored($decoded);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        $document = $value instanceof FieldDocument ? $value : FieldDocument::fromStored($value);
        if ($document === null || $document->isEmpty()) {
            return [$key => null];
        }

        return [$key => JsonHelper::encode($document->toArray())];
    }
}
