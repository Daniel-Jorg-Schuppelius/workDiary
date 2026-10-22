<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Zustand einer Gutschrift im Gutschriftsverfahren (MVP-1280). Ausgestellt ist unveränderlich. */
enum SelfBillingStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.invoicing.self-billing-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft => 'neutral',
            self::Issued => 'success',
            self::Cancelled => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Issued],
            self::Issued => [self::Cancelled],
            self::Cancelled => [],
        };
    }
}
