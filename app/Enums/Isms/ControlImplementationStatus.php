<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ControlImplementationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Umsetzungsstatus eines Controls (SoA-Spalte). */
enum ControlImplementationStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Partial = 'partial';
    case Implemented = 'implemented';
    case NotApplicable = 'notApplicable';

    public function label(): string {
        return (string) __('enums.isms.control-implementation-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Open => 'ghost',
            self::Partial => 'warning',
            self::Implemented => 'success',
            self::NotApplicable => 'neutral',
        };
    }
}
