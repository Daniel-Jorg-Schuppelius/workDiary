<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodKeyCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use App\Support\Period\PeriodKey;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Periodenschlüssel als Text (`2026`, `2026-H1`, `2026-Q3`, `2026-03`), gelesen
 * als {@see PeriodKey}. Gespeichert wird die kanonische Schreibweise; ein
 * ungültiger Wert wirft beim Schreiben (geprüft wird vorher im Request), beim
 * Lesen ergibt Altbestand ohne gültigen Schlüssel null.
 *
 * @implements CastsAttributes<PeriodKey, PeriodKey|string>
 */
final class PeriodKeyCast implements CastsAttributes {
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PeriodKey {
        return is_string($value) ? PeriodKey::tryParse($value) : null;
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof PeriodKey ? $value : PeriodKey::parse((string) $value))->key();
    }
}
