<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BicCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\ValueObjects\Bic;
use Illuminate\Database\Eloquent\Model;

/**
 * BIC-Spalte → {@see Bic}.
 *
 * Beim Schreiben bleibt die **erfasste Länge** erhalten: `Bic::getValue()`
 * liefert immer die elfstellige Form (`DEUTDEFF` → `DEUTDEFFXXX`), und eine
 * hinterlegte Bankverbindung soll sich nicht still ändern — Abgleiche mit
 * Lexoffice/DATEV und SEPA-Dateien vergleichen die Zeichenkette. Gespeichert
 * wird deshalb nur getrimmt und in Großbuchstaben; `asBic11()` liefert die
 * elfstellige Form dort, wo ein Format sie verlangt.
 *
 * @extends ValueObjectCast<Bic>
 */
class BicCast extends ValueObjectCast {
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function toValueObject(string $raw, Model $model, array $attributes): ?Bic {
        return Bic::tryFrom($raw);
    }

    protected function toStorage(object $value): string {
        /** @var Bic $value */
        return $value->getValue();
    }

    /**
     * Skalare Eingabe: nur normalisieren, nicht auf elf Stellen erweitern.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function storeScalar(string $raw, Model $model, array $attributes): string {
        $normalised = strtoupper(str_replace(' ', '', $raw));

        return $this->toValueObject($normalised, $model, $attributes) === null ? $raw : $normalised;
    }
}
