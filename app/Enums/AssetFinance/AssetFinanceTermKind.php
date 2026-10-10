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
            self::Rate => (string) __('enums.asset_finance.asset_finance_term_kind.rate'),
            self::SpecialPayment => (string) __('enums.asset_finance.asset_finance_term_kind.special_payment'),
            self::ResidualValue => (string) __('enums.asset_finance.asset_finance_term_kind.residual_value'),
            self::PurchaseOption => (string) __('enums.asset_finance.asset_finance_term_kind.purchase_option'),
            self::ServicePackage => (string) __('enums.asset_finance.asset_finance_term_kind.service_package'),
            self::Insurance => (string) __('enums.asset_finance.asset_finance_term_kind.insurance'),
            self::Maintenance => (string) __('enums.asset_finance.asset_finance_term_kind.maintenance'),
            self::Wear => (string) __('enums.asset_finance.asset_finance_term_kind.wear'),
            self::ReturnCost => (string) __('enums.asset_finance.asset_finance_term_kind.return_cost'),
            self::Fee => (string) __('enums.asset_finance.asset_finance_term_kind.fee'),
            self::Indexation => (string) __('enums.asset_finance.asset_finance_term_kind.indexation'),
        };
    }
}
