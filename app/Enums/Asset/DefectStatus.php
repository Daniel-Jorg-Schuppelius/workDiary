<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DefectStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Asset;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum DefectStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case InRepair = 'inRepair';
    case Resolved = 'resolved';
    case WrittenOff = 'writtenOff';

    public function label(): string {
        return (string) __('enums.asset.defect-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::InRepair => 'info',
            self::Resolved => 'success',
            self::WrittenOff => 'ghost',
        };
    }

    /** Offen = der Defekt ist noch wirksam (kann sperren). */
    public function isOpen(): bool {
        return in_array($this, [self::Open, self::InRepair], true);
    }

    public function isClosed(): bool {
        return in_array($this, [self::Resolved, self::WrittenOff], true);
    }
}
