<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalChargeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer Mietposition bis zur Faktura-Übergabe (MVP-266).
 * invoiced = lokaler Beleg erzeugt; transferred = externe Beleghoheit
 * (Lexoffice/DATEV), Belegnummer in external_reference.
 */
enum RentalChargeStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Released = 'released';
    case Invoiced = 'invoiced';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('Entwurf'),
            self::Released => (string) __('Freigegeben'),
            self::Invoiced => (string) __('Abgerechnet'),
            self::Transferred => (string) __('Extern übergeben'),
            self::Cancelled => (string) __('Storniert'),
        };
    }

    public function isSettled(): bool {
        return in_array($this, [self::Invoiced, self::Transferred], true);
    }
}
