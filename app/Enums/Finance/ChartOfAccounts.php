<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartOfAccounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kontenrahmen für den DATEV-Buchungsstapel (Feature 045, „Rechnungswesen":
 * SKR03/SKR04 organisationsbezogen konfigurieren). Steuert das Standard-
 * Erlöskonto-Default je Organisation.
 */
enum ChartOfAccounts: string implements HasLabel {
    use HasOptions;

    case Skr03 = 'skr03';
    case Skr04 = 'skr04';

    public function label(): string {
        return (string) __('enums.finance.chart-of-accounts.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Skr03 => 'info',
            self::Skr04 => 'neutral',
        };
    }

    /**
     * Konventionelles Standard-Erlöskonto (19 %, Inland) je Kontenrahmen.
     * SKR03 ⇒ 8400, SKR04 ⇒ 4400 (nur als Default, in der Org-Konfig
     * überschreibbar).
     */
    public function defaultRevenueAccount(): string {
        return match ($this) {
            self::Skr03 => '8400',
            self::Skr04 => '4400',
        };
    }

    /**
     * Konventionelles Erlöskonto für steuerfreie/0 %-Umsätze je Kontenrahmen.
     * SKR03 ⇒ 8200, SKR04 ⇒ 4200 (Default, überschreibbar).
     */
    public function defaultTaxFreeRevenueAccount(): string {
        return match ($this) {
            self::Skr03 => '8200',
            self::Skr04 => '4200',
        };
    }
}
