<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\User\Permission;

/**
 * Übergabekanal eines Übergabenachweises (Feature 045, „Getrennte
 * Übergabekanäle"): Zeit und Produkte/Materialien werden NIE gemischt.
 */
enum TransferChannel: string implements HasLabel {
    use HasOptions;

    case Time = 'time';
    case Material = 'material';

    public function label(): string {
        return (string) __('enums.finance.transfer-channel.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Time => 'info',
            self::Material => 'success',
        };
    }

    /** Kanal-spezifische Permission (Rollentrennung Zeit vs. Material). */
    public function permission(): Permission {
        return match ($this) {
            self::Time => Permission::FinanceTransferTime,
            self::Material => Permission::FinanceTransferMaterial,
        };
    }
}
