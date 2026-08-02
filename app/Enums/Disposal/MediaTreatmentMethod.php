<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaTreatmentMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Disposal;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Behandlungsverfahren für Datenträger (Feature 100, MVP-475) — dokumentiert
 * das außerhalb von workDiary durchgeführte Verfahren, ersetzt es nicht.
 */
enum MediaTreatmentMethod: string implements HasLabel {
    use HasOptions;

    case SoftwareWipe = 'software_wipe';
    case Degaussing = 'degaussing';
    case Shredding = 'shredding';
    case RemovedForDestruction = 'removed_for_destruction';

    public function label(): string {
        return match ($this) {
            self::SoftwareWipe => (string) __('Software-Löschung'),
            self::Degaussing => (string) __('Degaussing'),
            self::Shredding => (string) __('Schreddern'),
            self::RemovedForDestruction => (string) __('Ausgebaut zur Vernichtung'),
        };
    }
}
