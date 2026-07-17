<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceTermKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Konditionsart (MVP-272): strukturierte Vertragsbestandteile neben der
 * Grundrate — alles wird als Snapshot an der Akte eingefroren (P2).
 */
enum AssetFinanceTermKind: string implements HasLabel {
    use HasOptions;

    case Rate = 'rate';
    case SpecialPayment = 'special_payment';
    case ResidualValue = 'residual_value';
    case PurchaseOption = 'purchase_option';
    case ServicePackage = 'service_package';
    case Insurance = 'insurance';
    case Maintenance = 'maintenance';
    case Wear = 'wear';
    case ReturnCost = 'return_cost';
    case Fee = 'fee';
    case Indexation = 'indexation';

    public function label(): string {
        return match ($this) {
            self::Rate => (string) __('Rate'),
            self::SpecialPayment => (string) __('Sonderzahlung'),
            self::ResidualValue => (string) __('Restwertannahme'),
            self::PurchaseOption => (string) __('Kaufoption'),
            self::ServicePackage => (string) __('Servicepaket'),
            self::Insurance => (string) __('Versicherung'),
            self::Maintenance => (string) __('Wartung'),
            self::Wear => (string) __('Verschleiß'),
            self::ReturnCost => (string) __('Rückgabekosten'),
            self::Fee => (string) __('Gebühr'),
            self::Indexation => (string) __('Indexierung'),
        };
    }
}
