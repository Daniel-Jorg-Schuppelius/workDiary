<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeTariffKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Tarifart (MVP-849): Einzelbeitrag je Mitglied oder fester Haushaltstarif je Beitragskonto. */
enum ClubFeeTariffKind: string implements HasLabel {
    use HasOptions;

    case Individual = 'individual';
    case Family = 'family';

    public function label(): string {
        return (string) __('enums.club.fee-tariff-kind.' . $this->value);
    }
}
