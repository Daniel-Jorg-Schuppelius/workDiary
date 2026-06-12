<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Support-Status eines Softwareprodukts (Feature 044, Softwareinventar).
 * Liegt eol_on in der Vergangenheit, setzt der SoftwareInventoryService
 * beim Speichern automatisch EndOfLife.
 */
enum SupportStatus: string implements HasLabel {
    use HasOptions;

    case Supported = 'supported';
    case ExtendedSupport = 'extendedSupport';
    case EndOfLife = 'endOfLife';
    case Unknown = 'unknown';

    public function label(): string {
        return (string) __('enums.isms.support-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Supported => 'success',
            self::ExtendedSupport => 'warning',
            self::EndOfLife => 'error',
            self::Unknown => 'ghost',
        };
    }
}
