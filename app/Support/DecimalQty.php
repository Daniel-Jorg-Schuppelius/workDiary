<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecimalQty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Dezimalmengen-Helfer für die bcmath-Pfade (Lager/Fertigung/Beschaffung) —
 * Vollaudit 2026-07, M43/M47: ersetzt 11 byte-identische private
 * positive()-Kopien plus numeric()/negative()-Varianten. Kern delegiert an
 * CommonToolkit (normalizeDecimalString + absPrecise, toolkit-first); nur der
 * Nicht-numerisch-Guard ('' / !is_numeric → '0') ist App-Semantik.
 *
 * Verhaltensdelta zur alten bcmul(...,'-1',SCALE)-Kopie: absPrecise erhält die
 * Eingabe-Skala exakt statt auf SCALE zu trunkieren — Eingaben stammen aus
 * decimal(*,4)-Spalten, das Delta ist praktisch leer (Beleg M47).
 *
 * Die Clamp-Variante (MrpService: negativ → '0' statt Betrag) ist bewusst
 * KEIN Teil dieser Klasse — anderes Verhalten, nicht vermengen.
 */
final class DecimalQty {
    /** Kanonische Mengen-/Wertskala der bcmath-Pfade (ehem. 7× SCALE=4). */
    public const SCALE = 4;

    /**
     * Betrag (immer >= 0) als kanonischer Dezimalstring.
     *
     * @return numeric-string
     */
    public static function positive(string $value): string {
        return NumberHelper::absPrecise(NumberHelper::normalizeDecimalString($value));
    }

    /**
     * Negierter Betrag (immer <= 0) als kanonischer Dezimalstring.
     *
     * @return numeric-string
     */
    public static function negative(string $value): string {
        $abs = self::positive($value);

        /** @var numeric-string */
        return bccomp($abs, '0', self::SCALE) === 0 ? $abs : '-' . $abs;
    }
}
