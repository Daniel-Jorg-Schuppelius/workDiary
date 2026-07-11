<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

/**
 * Vertragsart (MVP-270). Klassifikationshinweise ohne bilanzielle oder
 * steuerliche Zusage — Zurechnung bleibt beim Rechnungswesen (W11).
 */
enum AssetFinanceKind: string {
    case OperatingLease = 'operating_lease';
    case FinanceLease = 'finance_lease';
    case HirePurchase = 'hire_purchase';
    case LongTermRent = 'long_term_rent';
    case UsageContract = 'usage_contract';
    case ServiceContract = 'service_contract';

    public function label(): string {
        return match ($this) {
            self::OperatingLease => (string) __('Operating-Leasing'),
            self::FinanceLease => (string) __('Finanzierungsleasing'),
            self::HirePurchase => (string) __('Mietkauf'),
            self::LongTermRent => (string) __('Langzeitmiete'),
            self::UsageContract => (string) __('Nutzungsvertrag'),
            self::ServiceContract => (string) __('Servicevertrag mit Asset-Bezug'),
        };
    }
}
