<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Lebenszyklus des Lieferscheins (Phase 145, MVP-1163). */
enum DeliveryNoteStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.billing.delivery_note_status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Delivered, self::Cancelled],
            self::Delivered => [self::Cancelled],
            self::Cancelled => [],
        };
    }
}
