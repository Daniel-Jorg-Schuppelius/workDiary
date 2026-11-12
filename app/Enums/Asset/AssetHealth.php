<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Asset;

use App\Enums\Contracts\HasLabel;

enum AssetHealth: string implements HasLabel {
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Critical = 'critical';

    public function label(): string {
        return (string) __('enums.asset.asset_health.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::Degraded => 'warning',
            self::Critical => 'error',
        };
    }
}
