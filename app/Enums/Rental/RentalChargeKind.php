<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalChargeKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer Miet- oder Zusatzposition (MVP-262/266). Kaution ist bewusst
 * KEINE Charge-Art — sie läuft als eigener Finanzvorgang (D10).
 */
enum RentalChargeKind: string implements HasLabel {
    use HasOptions;

    case DailyRate = 'daily_rate';
    case HourlyRate = 'hourly_rate';
    case FlatRate = 'flat_rate';
    case WeekendSurcharge = 'weekend_surcharge';
    case HolidaySurcharge = 'holiday_surcharge';
    case Cleaning = 'cleaning';
    case Consumable = 'consumable';
    case Delivery = 'delivery';
    case Damage = 'damage';
    case Loss = 'loss';
    case Discount = 'discount';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::DailyRate => (string) __('Tagessatz'),
            self::HourlyRate => (string) __('Stundensatz'),
            self::FlatRate => (string) __('Pauschale'),
            self::WeekendSurcharge => (string) __('Wochenendzuschlag'),
            self::HolidaySurcharge => (string) __('Feiertagszuschlag'),
            self::Cleaning => (string) __('Reinigung'),
            self::Consumable => (string) __('Verbrauchsmaterial'),
            self::Delivery => (string) __('Lieferung/Transport'),
            self::Damage => (string) __('Schaden'),
            self::Loss => (string) __('Verlust'),
            self::Discount => (string) __('Minderung/Nachlass'),
            self::Other => (string) __('Sonstiges'),
        };
    }

    /**
     * Schadens- und Verlustentscheidungen brauchen eine Pflichtbegründung.
     */
    public function requiresReason(): bool {
        return in_array($this, [self::Damage, self::Loss, self::Discount], true);
    }
}
