<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IbanCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\Iban;
use Illuminate\Database\Eloquent\Model;

/**
 * IBAN-Spalte → {@see Iban}. Gespeichert wird die kanonische Form ohne
 * Leerzeichen; für die Anzeige `formatted()`, für Auszüge `masked()`.
 * Verschlüsselte Spalten: `IbanCast::class . ':encrypted'`.
 *
 * @extends ValueObjectCast<Iban>
 */
class IbanCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?Iban {
        return Iban::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var Iban $value */
        return $value->getValue();
    }
}
