<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GermanTaxIdCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\GermanTaxId;
use Illuminate\Database\Eloquent\Model;

/**
 * Steuerliche Identifikationsnummer → {@see GermanTaxId} (11-stellig,
 * mit Prüfziffer). In der Personalakte verschlüsselt abgelegt.
 *
 * @extends ValueObjectCast<GermanTaxId>
 */
class GermanTaxIdCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?GermanTaxId {
        return GermanTaxId::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var GermanTaxId $value */
        return $value->getValue();
    }
}
