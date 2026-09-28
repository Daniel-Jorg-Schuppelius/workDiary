<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantPlanRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Platform;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Tarifwechsel-Anfrage eines Mandanten (MVP-957). */
enum TenantPlanRequestStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Done = 'done';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return (string) __('platform_usage.plan_request.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Done => 'success',
            self::Declined, self::Withdrawn => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Done, self::Declined, self::Withdrawn],
            self::Done, self::Declined, self::Withdrawn => [],
        };
    }
}
