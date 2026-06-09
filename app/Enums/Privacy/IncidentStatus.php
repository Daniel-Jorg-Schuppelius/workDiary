<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Bearbeitungsstand eines Datenschutzvorfalls. */
enum IncidentStatus: string {
    case Detected = 'detected';       // entdeckt, Erstaufnahme
    case Assessing = 'assessing';     // Risikobewertung läuft
    case Contained = 'contained';     // eingedämmt
    case Reported = 'reported';       // gemeldet (Behörde/Betroffene)
    case Closed = 'closed';           // abgeschlossen

    public function label(): string {
        return match ($this) {
            self::Detected => __('Entdeckt'),
            self::Assessing => __('In Bewertung'),
            self::Contained => __('Eingedämmt'),
            self::Reported => __('Gemeldet'),
            self::Closed => __('Abgeschlossen'),
        };
    }

    public function isOpen(): bool {
        return $this !== self::Closed;
    }
}
