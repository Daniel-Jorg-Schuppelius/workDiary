<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vermietungsart einer Wohnung (Branchenprofil Vermieter). Ein Mietprofil ohne
 * Art ist ein Leihgerät des Geräteverleihs.
 */
enum RentalMode: string implements HasLabel {
    use HasOptions;

    case LongTerm = 'long_term';
    case Holiday = 'holiday';
    case Both = 'both';

    public function label(): string {
        return (string) __('enums.rental.rental_mode.' . $this->value);
    }

    public function allowsLongTerm(): bool {
        return $this !== self::Holiday;
    }

    public function allowsHoliday(): bool {
        return $this !== self::LongTerm;
    }
}
