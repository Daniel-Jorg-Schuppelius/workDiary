<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallItemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Stand je betroffener Auslieferung einer Rückrufaktion (MVP-921). */
enum RecallItemStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Notified = 'notified';
    case Returned = 'returned';
    case Resolved = 'resolved';

    public function label(): string {
        return (string) __('recall.item_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Notified => 'info',
            self::Returned, self::Resolved => 'success',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Notified, self::Returned, self::Resolved],
            self::Notified => [self::Returned, self::Resolved],
            self::Returned => [self::Resolved],
            self::Resolved => [],
        };
    }
}
