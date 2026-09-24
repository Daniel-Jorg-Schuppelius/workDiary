<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasCustomFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Fields\CustomFieldValue;
use App\Services\Fields\{CustomFieldService, FieldSchema, FieldValues};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Träger benutzerdefinierter Felder (MVP-868, Vertrag
 * {@see \App\Models\Contracts\CustomFieldSubject}): Schema kommt aus der
 * Organisation ({@see CustomFieldService}), Werte hängen als ein Datensatz
 * am Träger.
 *
 * @mixin Model
 */
trait HasCustomFields {
    /** @return MorphOne<CustomFieldValue, $this> */
    public function customFieldValue(): MorphOne {
        return $this->morphOne(CustomFieldValue::class, 'subject');
    }

    public function customFieldSchema(): FieldSchema {
        return app(CustomFieldService::class)->schemaFor(static::class, (int) $this->getAttribute('organization_id'));
    }

    public function customValues(): FieldValues {
        $row = $this->getRelationValue('customFieldValue');

        return $row instanceof CustomFieldValue ? $row->values : new FieldValues;
    }

    /** @param  array<string, mixed>  $input  Eingabe `custom[<key>]` */
    public function syncCustomFields(array $input): void {
        app(CustomFieldService::class)->sync($this, $input);
    }

    /** @return list<string> Textwerte für Suche und Export. */
    public function customFieldTexts(): array {
        return app(CustomFieldService::class)->texts($this);
    }
}
