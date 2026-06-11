<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RiskCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Kategorie eines ISMS-Risikos (Feature 044, MVP 1). */
enum RiskCategory: string implements HasLabel {
    use HasOptions;

    case Organizational = 'organizational';
    case Technical = 'technical';
    case Physical = 'physical';
    case Personnel = 'personnel';
    case Supplier = 'supplier';

    public function label(): string {
        return (string) __('enums.isms.risk-category.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Organizational => 'info',
            self::Technical => 'primary',
            self::Physical => 'warning',
            self::Personnel => 'secondary',
            self::Supplier => 'accent',
        };
    }
}
