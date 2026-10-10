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
            self::DailyRate => (string) __('enums.rental.rental_charge_kind.daily_rate'),
            self::HourlyRate => (string) __('enums.rental.rental_charge_kind.hourly_rate'),
            self::FlatRate => (string) __('enums.rental.rental_charge_kind.flat_rate'),
            self::WeekendSurcharge => (string) __('enums.rental.rental_charge_kind.weekend_surcharge'),
            self::HolidaySurcharge => (string) __('enums.rental.rental_charge_kind.holiday_surcharge'),
            self::Cleaning => (string) __('enums.rental.rental_charge_kind.cleaning'),
            self::Consumable => (string) __('enums.rental.rental_charge_kind.consumable'),
            self::Delivery => (string) __('enums.rental.rental_charge_kind.delivery'),
            self::Damage => (string) __('enums.rental.rental_charge_kind.damage'),
            self::Loss => (string) __('enums.rental.rental_charge_kind.loss'),
            self::Discount => (string) __('enums.rental.rental_charge_kind.discount'),
            self::Other => (string) __('enums.rental.rental_charge_kind.other'),
        };
    }

    /**
     * Schadens- und Verlustentscheidungen brauchen eine Pflichtbegründung.
     */
    public function requiresReason(): bool {
        return in_array($this, [self::Damage, self::Loss, self::Discount], true);
    }
}
