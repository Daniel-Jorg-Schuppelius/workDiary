<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffFormula.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Takeoff;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Formeln des Aufmaßblatts (MVP-1058) — Werte sind die Nummern des
 * REB-Katalogs 23.003, gerechnet vom `GaebTakeoffCalculator`. Eine
 * Flächenformel wird mit einem weiteren Wert zum Körper (Höhe/Tiefe).
 */
enum TakeoffFormula: string implements HasLabel {
    use HasOptions;

    case Sum = '00';
    case Triangle = '01';
    case Rectangle = '04';
    case Trapezoid = '05';
    case Circle = '07';
    case Mean = '31';
    case Free = '91';

    public function label(): string {
        return (string) __('takeoff.formula.' . $this->name);
    }

    /**
     * Bezeichnungen der Eingabewerte in REB-Reihenfolge; der letzte ist bei
     * Flächenformeln optional (Körper).
     *
     * @return list<string>
     */
    public function valueLabels(): array {
        return array_map(static fn (string $key): string => (string) __('takeoff.value.' . $key), match ($this) {
            self::Sum => ['amount'],
            self::Triangle => ['base', 'height', 'depth'],
            self::Rectangle => ['length', 'width', 'depth'],
            self::Trapezoid => ['side_a', 'side_c', 'height', 'depth'],
            self::Circle => ['radius', 'angle', 'depth'],
            self::Mean => ['amount'],
            self::Free => ['expression'],
        });
    }

    /** Wie viele Werte mindestens gebraucht werden. */
    public function requiredValues(): int {
        return match ($this) {
            self::Sum, self::Mean, self::Free => 1,
            self::Triangle, self::Rectangle, self::Circle => 2,
            self::Trapezoid => 3,
        };
    }

    /** Summe und Mittel nehmen beliebig viele Werte (eine Liste). */
    public function isList(): bool {
        return $this === self::Sum || $this === self::Mean;
    }

    public function isExpression(): bool {
        return $this === self::Free;
    }
}
