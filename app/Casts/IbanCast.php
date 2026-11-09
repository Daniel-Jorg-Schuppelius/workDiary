<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IbanCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use CommonToolkit\ValueObjects\Iban;

/**
 * IBAN-Spalte: gespeichert wird die kanonische Form ohne Leerzeichen in
 * Großbuchstaben, gelesen Text; das Wertobjekt für `formatted()`/`masked()`
 * liefert `Iban::tryFrom()`. Verschlüsselte Spalten: `IbanCast::class . ':encrypted'`.
 *
 * @extends IdentifierCast<Iban>
 */
class IbanCast extends IdentifierCast {
    protected function toValueObject(string $raw): ?Iban {
        return Iban::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var Iban $value */
        return $value->getValue();
    }
}
