<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BicCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use CommonToolkit\Helper\Data\BankHelper;
use CommonToolkit\ValueObjects\Bic;

/**
 * BIC-Spalte.
 *
 * Beim Schreiben bleibt die **erfasste Länge** erhalten: `Bic::getValue()`
 * liefert immer die elfstellige Form (`DEUTDEFF` → `DEUTDEFFXXX`), und eine
 * hinterlegte Bankverbindung soll sich nicht still ändern — Abgleiche mit
 * Lexoffice/DATEV und SEPA-Dateien vergleichen die Zeichenkette. Gespeichert
 * wird deshalb nur getrimmt und in Großbuchstaben; `asBic11()` liefert die
 * elfstellige Form dort, wo ein Format sie verlangt.
 *
 * @extends IdentifierCast<Bic>
 */
class BicCast extends IdentifierCast {
    protected function toValueObject(string $raw): ?Bic {
        return Bic::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var Bic $value */
        return $value->getValue();
    }

    protected function normalise(string $raw): string {
        $normalised = BankHelper::normalizeBIC($raw) ?? '';

        return $normalised !== '' && $this->toValueObject($normalised) !== null ? $normalised : $raw;
    }
}
