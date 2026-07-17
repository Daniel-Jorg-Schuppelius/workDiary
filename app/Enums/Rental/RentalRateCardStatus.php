<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateCardStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Versionierte Preislisten (D10): retired-Versionen bleiben lesbar,
 * alte Verleihfälle werden nie umbewertet.
 */
enum RentalRateCardStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('Entwurf'),
            self::Active => (string) __('Aktiv'),
            self::Retired => (string) __('Abgelöst'),
        };
    }
}
