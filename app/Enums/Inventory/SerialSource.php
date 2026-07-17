<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft einer Seriennummer: selbst erzeugt (Eigenfertigung) oder beim
 * Wareneingang erfasst (Zukauf).
 */
enum SerialSource: string implements HasLabel {
    use HasOptions;

    case Manufactured = 'manufactured';
    case Purchased = 'purchased';

    public function label(): string {
        return __('inventory.serial.source.' . $this->value);
    }
}
