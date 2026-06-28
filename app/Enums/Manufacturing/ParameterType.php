<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParameterType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

/**
 * Typ eines Auftragsparameters einer Arbeitsplan-Version (Feature 047, MVP-061).
 *
 * - Number:  freie Zahl (min/max in den Constraints)
 * - Measure: Zahl mit Einheit (Constraint `unit`, plus min/max)
 * - Choice:  Auswahl aus `options`
 * - Text:    Freitext
 * - Date:    Datum (Y-m-d)
 * - Bool:    Ja/Nein
 */
enum ParameterType: string {
    case Number = 'number';
    case Measure = 'measure';
    case Choice = 'choice';
    case Text = 'text';
    case Date = 'date';
    case Bool = 'bool';

    public function label(): string {
        return __('manufacturing.parameter_type.' . $this->value);
    }

    /** Numerische Typen (min/max-Prüfung anwendbar). */
    public function isNumeric(): bool {
        return $this === self::Number || $this === self::Measure;
    }
}
