<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ByteSizeCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\ByteSize;
use Illuminate\Database\Eloquent\Model;

/**
 * Größenspalte (Bytes) → {@see ByteSize}.
 *
 * Die Spalte bleibt `integer`; `format()` liefert die lesbare Größe, ohne dass
 * jede View erneut durch 1024 teilt.
 *
 * @extends ValueObjectCast<ByteSize>
 */
class ByteSizeCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?ByteSize {
        return ctype_digit($raw) ? ByteSize::ofBytes((int) $raw) : null;
    }

    protected function toStorage(object $value): string {
        /** @var ByteSize $value */
        return (string) $value->getBytes();
    }
}
