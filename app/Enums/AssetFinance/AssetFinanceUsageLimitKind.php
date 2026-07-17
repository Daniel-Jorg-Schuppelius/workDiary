<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceUsageLimitKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Nutzungslimit-Art (MVP-275): Ist-Werte werden nur referenziert
 * (Zählerstände/manuelle Erfassung), nie gebucht (D11).
 */
enum AssetFinanceUsageLimitKind: string implements HasLabel {
    use HasOptions;

    case Kilometers = 'kilometers';
    case OperatingHours = 'operating_hours';
    case UsageDays = 'usage_days';

    public function label(): string {
        return match ($this) {
            self::Kilometers => (string) __('Kilometer'),
            self::OperatingHours => (string) __('Betriebsstunden'),
            self::UsageDays => (string) __('Nutzungstage'),
        };
    }
}
