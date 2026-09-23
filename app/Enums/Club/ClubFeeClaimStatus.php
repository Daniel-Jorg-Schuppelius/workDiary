<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeClaimStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Forderungsstatus (MVP-850/851): offen, teilweise bezahlt, bezahlt, storniert.
 * Exportiert oder zum Einzug vorgemerkt bedeutet niemals bezahlt; eine
 * Rücklastschrift öffnet den Restbetrag wieder.
 */
enum ClubFeeClaimStatus: string implements HasStatusTransitions {
    use HasOptions;

    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.club.fee-claim-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Cancelled => 'ghost',
        };
    }

    public function isOpen(): bool {
        return $this === self::Open || $this === self::PartiallyPaid;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::PartiallyPaid, self::Paid, self::Cancelled],
            self::PartiallyPaid => [self::Paid, self::Open, self::Cancelled],
            self::Paid => [self::Open, self::PartiallyPaid],
            self::Cancelled => [],
        };
    }
}
