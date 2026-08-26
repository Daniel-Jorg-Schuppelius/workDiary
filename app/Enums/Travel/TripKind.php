<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TripKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Travel;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Fahrtart im steuerlichen Fahrtenbuch (Feature 137): betrieblich,
 * Wohnung–Arbeitsstätte, privat. Kein Status — keine Übergänge.
 */
enum TripKind: string implements HasLabel {
    use HasOptions;

    case Business = 'business';
    case Commute = 'commute';
    case Private_ = 'private';

    public function label(): string {
        return (string) __('travel.trip_kind.' . $this->value);
    }
}
