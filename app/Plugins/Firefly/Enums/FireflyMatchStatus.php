<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyMatchStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Zuordnung einer Bewegung über eine Belegnummer im Verwendungszweck. */
enum FireflyMatchStatus: string implements HasLabel {
    use HasOptions;

    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ambiguous = 'ambiguous';

    public function label(): string {
        return (string) __('firefly::enums.match_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Matched => 'success',
            self::Ambiguous => 'warning',
            self::Unmatched => 'plain',
        };
    }
}
