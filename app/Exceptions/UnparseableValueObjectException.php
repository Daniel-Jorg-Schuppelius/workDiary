<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnparseableValueObjectException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use UnexpectedValueException;

/**
 * Ein numerischer Wertobjekt-Cast (Money, Percentage, Quantity, Decimal,
 * ByteSize) bekam beim Schreiben einen nicht deutbaren Wert. Bis 2026-09-19
 * landete der Rohtext still in der DECIMAL-Spalte („19.00 %" → gelesen null,
 * unter MariaDB strict ein Datenbankfehler) — jetzt fällt er an der Quelle auf.
 */
class UnparseableValueObjectException extends UnexpectedValueException {
    public function __construct(
        public readonly string $attribute,
        public readonly string $value,
        string $type,
    ) {
        parent::__construct("Wert für {$attribute} ist kein gültiger {$type}: '{$value}'");
    }
}
