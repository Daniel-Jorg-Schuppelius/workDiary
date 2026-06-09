<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImplementationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Umsetzungsstatus einer technischen/organisatorischen Maßnahme. */
enum ImplementationStatus: string {
    case Planned = 'planned';
    case Partial = 'partial';
    case Implemented = 'implemented';
    case NotApplicable = 'not_applicable';

    public function label(): string {
        return match ($this) {
            self::Planned => __('Geplant'),
            self::Partial => __('Teilweise umgesetzt'),
            self::Implemented => __('Umgesetzt'),
            self::NotApplicable => __('Nicht anwendbar'),
        };
    }
}
