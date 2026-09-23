<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePositionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Beitragsposition (MVP-849): Grundbeitrag, Familienbeitrag je Konto, Abteilungszuschlag, Aufnahmegebühr. */
enum ClubFeePositionKind: string implements HasLabel {
    use HasOptions;

    case Base = 'base';
    case Family = 'family';
    case Surcharge = 'surcharge';
    case Admission = 'admission';
    // Meldegebühr eines Wettkampfs (MVP-855) als eigene Beitragsposition.
    case Entry = 'entry';

    public function label(): string {
        return (string) __('enums.club.fee-position-kind.' . $this->value);
    }
}
