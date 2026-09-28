<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Zuwendung (MVP-1003). Mitgliedsbeiträge sind nur bestätigungsfähig,
 * wenn die Organisation es einschaltet — für Sport- und Freizeitzwecke schließt
 * § 10b Abs. 1 Satz 8 EStG den Abzug aus.
 */
enum ClubDonationKind: string implements HasLabel {
    use HasOptions;

    case Donation = 'donation';
    case MembershipFee = 'membership_fee';

    public function label(): string {
        return (string) __('enums.club.donation-kind.' . $this->value);
    }
}
