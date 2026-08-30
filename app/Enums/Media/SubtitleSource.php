<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubtitleSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Media;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft einer Untertitelspur (Feature 150).
 *
 * Der Unterschied ist kein Schönheitsfehler: eine maschinelle Spur ist erst
 * nach menschlicher Durchsicht ein Nachweis nach WCAG 1.2.2. Bis dahin wird
 * sie ausgespielt — aber sichtbar als das, was sie ist.
 */
enum SubtitleSource: string implements HasLabel {
    use HasOptions;

    case Manual = 'manual';
    case Machine = 'machine';

    public function label(): string {
        return (string) __('enums.media.subtitle-source.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Manual => 'success',
            self::Machine => 'warning',
        };
    }

    /** Braucht diese Herkunft eine Durchsicht, bevor sie als Nachweis zählt? */
    public function needsReview(): bool {
        return $this === self::Machine;
    }
}
