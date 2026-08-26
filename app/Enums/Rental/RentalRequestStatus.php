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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer Portal-Verleihanfrage (Feature 073, MVP-714):
 * requested → accepted | declined; der Kunde kann eine offene Anfrage
 * zurücknehmen (withdrawn). Entschiedene Anfragen sind unveränderlich.
 */
enum RentalRequestStatus: string implements HasLabel {
    use HasOptions;

    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return match ($this) {
            self::Requested => (string) __('angefragt'),
            self::Accepted => (string) __('angenommen'),
            self::Declined => (string) __('abgelehnt'),
            self::Withdrawn => (string) __('zurückgenommen'),
        };
    }

    public function badgeTone(): string {
        return match ($this) {
            self::Requested => 'info',
            self::Accepted => 'success',
            self::Declined => 'error',
            self::Withdrawn => 'ghost',
        };
    }

    public function isOpen(): bool {
        return $this === self::Requested;
    }
}
