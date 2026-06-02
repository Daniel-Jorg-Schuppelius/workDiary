<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidExists.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use App\Services\SqidEncoder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Prüft, dass ein Sqid-Eingabewert auf einen existierenden Datensatz
 * des angegebenen Modells mappt.
 *
 * Hinweis: Wenn der Eingabewert über `DecodesSqidInputs` bereits zu
 * einem int dekodiert wurde, akzeptiert die Rule auch int-Werte.
 */
final readonly class SqidExists implements ValidationRule {
    /**
     * @param  class-string  $modelClass
     */
    public function __construct(
        private string $modelClass,
        private ?string $column = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if ($value === null || $value === '') {
            return; // required wird separat validiert
        }

        $id = match (true) {
            is_int($value) => $value,
            is_string($value) && ctype_digit($value) => (int) $value,
            is_string($value) => app(SqidEncoder::class)->decode($this->modelClass, $value),
            default => null,
        };

        if ($id === null || $id <= 0) {
            $fail(__('validation.exists', ['attribute' => $attribute]));

            return;
        }

        $instance = new $this->modelClass();
        $column = $this->column ?? (method_exists($instance, 'getKeyName') ? $instance->getKeyName() : 'id');
        $exists = $this->modelClass::query()->where($column, $id)->exists();

        if (! $exists) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
