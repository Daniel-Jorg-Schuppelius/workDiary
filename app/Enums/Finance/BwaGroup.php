<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BwaGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zeile der betriebswirtschaftlichen Auswertung (Feature 142, MVP-709).
 *
 * Die Reihenfolge der Cases ist die Reihenfolge im Bericht; die Zwischen-
 * summen (Gesamtleistung, Rohertrag, Betriebsergebnis, Ergebnis vor Steuern)
 * ergeben sich aus {@see self::section()}. Ertragsgruppen zählen positiv,
 * Aufwandsgruppen positiv als Kosten — das Vorzeichen des Kontos wird beim
 * Einsortieren gedreht, nicht in der Datenbank.
 */
enum BwaGroup: string implements HasLabel {
    use HasOptions;

    case Revenue = 'revenue';
    case InventoryChange = 'inventory_change';
    case Material = 'material';
    case OtherOperatingIncome = 'other_operating_income';
    case Personnel = 'personnel';
    case Premises = 'premises';
    case OperatingTaxes = 'operating_taxes';
    case InsuranceFees = 'insurance_fees';
    case Vehicle = 'vehicle';
    case MarketingTravel = 'marketing_travel';
    case GoodsDispatch = 'goods_dispatch';
    case Depreciation = 'depreciation';
    case Repairs = 'repairs';
    case OtherCosts = 'other_costs';
    case InterestExpense = 'interest_expense';
    case NeutralExpense = 'neutral_expense';
    case InterestIncome = 'interest_income';
    case NeutralIncome = 'neutral_income';
    case IncomeTaxes = 'income_taxes';

    public function label(): string {
        return (string) __('enums.finance.bwa-group.' . $this->value);
    }

    /** Ertragsgruppe (Haben − Soll) oder Aufwandsgruppe (Soll − Haben)? */
    public function isIncome(): bool {
        return in_array($this, [
            self::Revenue,
            self::InventoryChange,
            self::OtherOperatingIncome,
            self::InterestIncome,
            self::NeutralIncome,
        ], true);
    }

    /**
     * Abschnitt der BWA: output → Gesamtleistung, material → Rohertrag,
     * other_income → betrieblicher Rohertrag, costs → Betriebsergebnis,
     * neutral → Ergebnis vor Steuern, taxes → vorläufiges Ergebnis.
     */
    public function section(): string {
        return match ($this) {
            self::Revenue, self::InventoryChange => 'output',
            self::Material => 'material',
            self::OtherOperatingIncome => 'other_income',
            self::Personnel, self::Premises, self::OperatingTaxes, self::InsuranceFees, self::Vehicle,
            self::MarketingTravel, self::GoodsDispatch, self::Depreciation, self::Repairs, self::OtherCosts => 'costs',
            self::InterestExpense, self::NeutralExpense, self::InterestIncome, self::NeutralIncome => 'neutral',
            self::IncomeTaxes => 'taxes',
        };
    }

    public function tone(): string {
        return $this->isIncome() ? 'success' : 'error';
    }
}
