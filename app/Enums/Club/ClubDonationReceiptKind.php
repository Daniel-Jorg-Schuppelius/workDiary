<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationReceiptKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Einzel- oder Sammelbestätigung über Zuwendungen eines Jahres (MVP-1003). */
enum ClubDonationReceiptKind: string implements HasLabel {
    use HasOptions;

    case Single = 'single';
    case Collective = 'collective';

    public function label(): string {
        return (string) __('enums.club.donation-receipt-kind.' . $this->value);
    }
}
