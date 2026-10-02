<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Stand einer Online-Zahlung beim Zahlungsanbieter (MVP-1067). */
enum OnlinePaymentStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Expired = 'expired';
    /** Vollständig erstattet; Teilerstattungen führt `refunded_amount` an einer bezahlten Zahlung. */
    case Refunded = 'refunded';

    public function label(): string {
        return (string) __('enums.invoicing.online-payment-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'info',
            self::Paid => 'success',
            self::Failed => 'error',
            self::Canceled, self::Expired => 'ghost',
            self::Refunded => 'warning',
        };
    }

    /** Deckt die Zahlung die Rechnung (abzüglich Erstattungen)? */
    public function settles(): bool {
        return $this === self::Paid;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Paid, self::Failed, self::Canceled, self::Expired],
            // Der Anbieter ist die Wahrheit: eine spät abgeschlossene Zahlung zählt.
            self::Failed, self::Canceled, self::Expired => [self::Paid],
            self::Paid => [self::Refunded],
            self::Refunded => [],
        };
    }
}
