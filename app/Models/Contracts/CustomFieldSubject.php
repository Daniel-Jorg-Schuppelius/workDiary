<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldSubject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Models\Fields\CustomFieldValue;
use App\Services\Fields\{FieldSchema, FieldValues};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Träger benutzerdefinierter Felder (MVP-868); Implementierung liefert der
 * Trait {@see \App\Models\Concerns\HasCustomFields}.
 */
interface CustomFieldSubject {
    /** @return MorphOne<CustomFieldValue, covariant Model> */
    public function customFieldValue(): MorphOne;

    public function customFieldSchema(): FieldSchema;

    public function customValues(): FieldValues;

    /** @param  array<string, mixed>  $input */
    public function syncCustomFields(array $input): void;

    /** @return list<string> */
    public function customFieldTexts(): array;
}
