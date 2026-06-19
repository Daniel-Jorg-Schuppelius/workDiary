<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

/**
 * Statusmaschine einer Bestellung (Feature 048, E4):
 * draft → ordered → partially_received → received, jederzeit cancelled.
 */
enum PurchaseOrderStatus: string {
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('procurement.status.' . $this->value);
    }

    public function isTerminal(): bool {
        return $this === self::Received || $this === self::Cancelled;
    }

    /** @return list<self> */
    public function allowedNext(): array {
        return match ($this) {
            self::Draft => [self::Ordered, self::Cancelled],
            self::Ordered => [self::PartiallyReceived, self::Received, self::Cancelled],
            self::PartiallyReceived => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedNext(), true);
    }
}
