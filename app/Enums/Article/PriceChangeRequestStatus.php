<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceChangeRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Article;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Preisfreigabe-Antrags (Feature 050, MVP-095): verfällt, wenn der Vorschlag bei der Entscheidung nicht mehr stimmt. */
enum PriceChangeRequestStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string {
        return (string) __('procurement.approval.status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Expired],
            self::Approved, self::Rejected, self::Expired => [],
        };
    }
}
