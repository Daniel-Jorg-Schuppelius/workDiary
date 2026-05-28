<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecodesSqidInputs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\SqidEncoder;

/**
 * Trait für FormRequests, die opake Sqid-Strings in numerische
 * Foreign-Keys umwandeln. Die Dekodierung erfolgt in `validationData()`,
 * so dass das ursprüngliche Sqid-Input erhalten bleibt und beim
 * `withInput()`-Flash (z. B. nach Validierungsfehlern) wieder als
 * Sqid in den Formularen ankommt.
 *
 * Verwendung:
 *
 *     class StoreXyzRequest extends FormRequest {
 *         use DecodesSqidInputs;
 *
 *         protected array $sqidFields = [
 *             'customer_id'      => \App\Models\Customer::class,
 *             'assigned_user_id' => \App\Models\User::class,
 *             'tag_ids'          => \App\Models\Tag::class, // Arrays werden elementweise dekodiert
 *         ];
 *     }
 *
 * Werte:
 * - leerer String / null bleiben unverändert
 * - ungültige Sqids werden zu null (Validation soll dann via `exists`/`required` ablehnen)
 * - Arrays werden elementweise dekodiert
 */
trait DecodesSqidInputs {
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
        $fields = $this->sqidFields();

        if ($fields === []) {
            return $data;
        }

        /** @var SqidEncoder $encoder */
        $encoder = app(SqidEncoder::class);

        foreach ($fields as $field => $modelClass) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $data[$field] = array_values(array_map(
                    static fn($v) => is_string($v) && $v !== '' ? $encoder->decode($modelClass, $v) : (is_int($v) ? $v : null),
                    $value,
                ));
                continue;
            }

            if (is_string($value)) {
                $data[$field] = $encoder->decode($modelClass, $value);
            }
        }

        return $data;
    }
}
