<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecodesSqidOrNumericInputs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Sqid;

/**
 * Variante von {@see DecodesSqidInputs} mit numerischem Fallback: Die
 * Dekodierung erfolgt ebenfalls erst in `validationData()` (das Original-
 * Input bleibt so für den `withInput()`-Flash erhalten), nutzt aber
 * {@see Sqid::decodeOrNumeric()} statt des strikten Decoders — rohe
 * numerische IDs (alte Bookmarks/Links/API-Clients vor der Sqid-Umstellung)
 * bleiben wie in den bisherigen Controller-Merges gültig.
 *
 * Verwendung wie beim strikten Trait über die Property `$sqidFields`
 * (nur skalare Felder; Arrays werden hier bewusst nicht dekodiert).
 */
trait DecodesSqidOrNumericInputs {
    /**
     * Mapping `requestField => modelClass`. Standardmäßig wird die
     * Property `$sqidFields` ausgelesen; Klassen können die Methode
     * überschreiben, wenn dynamische Mappings benötigt werden.
     *
     * @return array<string, class-string>
     */
    protected function sqidFields(): array {
        /** @var array<string, class-string> $fields */
        $fields = $this->sqidFields;

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array {
        $data = parent::validationData();

        foreach ($this->sqidFields() as $field => $modelClass) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null || $value === '') {
                continue;
            }

            if (is_string($value) || is_int($value)) {
                $data[$field] = Sqid::decodeOrNumeric($modelClass, $value);
            }
        }

        return $data;
    }
}
