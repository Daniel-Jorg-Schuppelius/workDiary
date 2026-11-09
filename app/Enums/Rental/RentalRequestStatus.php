<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Lebenszyklus einer Portal-Verleihanfrage (Feature 073, MVP-714):
 * requested → accepted | declined; der Kunde kann eine offene Anfrage
 * zurücknehmen (withdrawn). Eine Direktbuchung mit Online-Vorauszahlung
 * (MVP-1515) wartet auf den Zahlungseingang (awaiting_payment) und wird erst
 * dann bestätigt (accepted); ohne Zahlung bis zur Frist verfällt sie (expired).
 * Entschiedene Anfragen sind unveränderlich.
 */
enum RentalRequestStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Requested = 'requested';
    case AwaitingPayment = 'awaiting_payment';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function label(): string {
        return (string) __('enums.rental.rental_request_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'info',
            self::AwaitingPayment => 'warning',
            self::Accepted => 'success',
            self::Declined => 'error',
            self::Withdrawn, self::Expired => 'ghost',
        };
    }

    public function isOpen(): bool {
        return $this === self::Requested;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Accepted, self::Declined, self::Withdrawn],
            self::AwaitingPayment => [self::Accepted, self::Expired, self::Withdrawn],
            self::Accepted, self::Declined, self::Withdrawn, self::Expired => [],
        };
    }
}
