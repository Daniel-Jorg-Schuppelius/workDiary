<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaOutcome.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Ergebnis einer Datenschutz-Folgenabschätzung (Art. 35/36). */
enum DpiaOutcome: string {
    case Open = 'open';                       // noch offen
    case Proceed = 'proceed';                 // Restrisiko vertretbar
    case ConsultAuthority = 'consult';        // vorherige Konsultation (Art. 36)
    case Abort = 'abort';                     // Verarbeitung nicht durchführbar

    public function label(): string {
        return match ($this) {
            self::Open => __('Offen'),
            self::Proceed => __('Vertretbar – Durchführung'),
            self::ConsultAuthority => __('Konsultation der Aufsichtsbehörde'),
            self::Abort => __('Nicht durchführbar'),
        };
    }
}
