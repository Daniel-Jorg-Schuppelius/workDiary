<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobCriticality.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Betriebliche Einstufung eines Registry-Jobs (Feature 067, MVP-175).
 * Steuert Anzeige-Gewichtung und Überfälligkeits-Bewertung.
 */
enum JobCriticality: string implements HasLabel {
    use HasOptions;

    case Core = 'core';
    case Integration = 'integration';
    case Housekeeping = 'housekeeping';

    public function label(): string {
        return __('scheduler.criticality.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Core => 'error',
            self::Integration => 'warning',
            self::Housekeeping => 'neutral',
        };
    }
}
