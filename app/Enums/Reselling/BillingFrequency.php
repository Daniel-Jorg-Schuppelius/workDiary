<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingFrequency.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;
use Carbon\CarbonImmutable;

/**
 * Abrechnungsrhythmus eines Marketplace-Abos (Spalte „Active Order Frequency").
 */
enum BillingFrequency: string implements HasLabel {
    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public static function fromLabel(string $label): ?self {
        $normalized = mb_strtolower(trim($label));

        return match ($normalized) {
            'jährlich', 'jaehrlich', 'yearly', 'annual', 'annually', 'year' => self::Yearly,
            'monatlich', 'monthly', 'month' => self::Monthly,
            default => null,
        };
    }

    public function advance(CarbonImmutable $date): CarbonImmutable {
        return match ($this) {
            self::Yearly => $date->addYearNoOverflow(),
            self::Monthly => $date->addMonthNoOverflow(),
        };
    }

    /**
     * Restlaufzeiten unterhalb dieser Länge sind Ausrichtungs-Stummel (die
     * Marketplace-Laufzeit endet am Jahrestag der Erstbestellung, nicht am
     * Jahrestag der Position) und keine eigene Abrechnungsperiode.
     */
    public function minimumPeriodDays(): int {
        return match ($this) {
            self::Yearly => 31,
            self::Monthly => 5,
        };
    }

    public function label(): string {
        return match ($this) {
            self::Yearly => 'jährlich',
            self::Monthly => 'monatlich',
        };
    }
}
