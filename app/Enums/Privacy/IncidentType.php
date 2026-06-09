<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Art eines Datenschutzvorfalls (Verletzung des Schutzes personenbezogener Daten). */
enum IncidentType: string {
    case Loss = 'loss';                       // Verlust
    case Misdelivery = 'misdelivery';         // Fehlversand
    case UnauthorizedAccess = 'unauthorized'; // unberechtigter Zugriff
    case Disclosure = 'disclosure';           // Offenlegung
    case Alteration = 'alteration';           // unbefugte Veränderung
    case Unavailability = 'unavailability';   // Nichtverfügbarkeit

    public function label(): string {
        return match ($this) {
            self::Loss => __('Verlust'),
            self::Misdelivery => __('Fehlversand'),
            self::UnauthorizedAccess => __('Unberechtigter Zugriff'),
            self::Disclosure => __('Offenlegung'),
            self::Alteration => __('Unbefugte Veränderung'),
            self::Unavailability => __('Nichtverfügbarkeit'),
        };
    }
}
