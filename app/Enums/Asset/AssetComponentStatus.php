<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComponentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Asset;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines verbauten Teils (Feature 118, MVP-607). */
enum AssetComponentStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Installed = 'installed';
    case Removed = 'removed';
    case Replaced = 'replaced';

    public function label(): string {
        return (string) __('asset.components.status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Installed => [self::Removed, self::Replaced],
            self::Removed, self::Replaced => [],
        };
    }
}
