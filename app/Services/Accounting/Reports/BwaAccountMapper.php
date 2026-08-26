<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BwaAccountMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{AccountType, BwaGroup, EuerCategory};
use App\Models\Accounting\AccountingAccount;

/**
 * Konto → BWA-Zeile (Feature 142, MVP-709).
 *
 * Reihenfolge der Entscheidung:
 *  1. ausdrückliche Zuordnung am Konto (`bwa_group`),
 *  2. belastbare Kategorisierung: EÜR-Zeile Abschreibung/GWG → Abschreibungen,
 *  3. Nummernkreis des erkannten Kontenrahmens (SKR03/SKR04),
 *  4. sonst **nicht zugeordnet** — der Bericht weist das sichtbar aus.
 *
 * Die Kontoart (Ertrag/Aufwand) reicht als Kategorisierung nicht: Sie kennt
 * weder Personal noch Raum noch Kfz. Der Kontenrahmen ist im Profil nicht
 * hinterlegt und wird aus dem Erlös-Nummernkreis der Organisation erkannt —
 * ein eigener Kontenplan ohne SKR-Logik bleibt damit ehrlich ungemappt.
 *
 * Die Nummernkreise folgen der Gliederung der Standardkontenrahmen (eigene
 * Zusammenstellung, vgl. `database/data/chartofaccounts/`); Randbereiche wie
 * Gewerbesteuer (SKR03 4320) sind bewusst vereinfacht und über `bwa_group`
 * je Konto korrigierbar.
 */
final class BwaAccountMapper {
    public const SCHEME_SKR03 = 'skr03';

    public const SCHEME_SKR04 = 'skr04';

    /** @var list<array{int, int, BwaGroup}> */
    private const SKR03 = [
        [2000, 2099, BwaGroup::NeutralExpense],
        [2100, 2199, BwaGroup::InterestExpense],
        [2200, 2299, BwaGroup::IncomeTaxes],
        [2300, 2499, BwaGroup::NeutralExpense],
        [2500, 2599, BwaGroup::NeutralIncome],
        [2600, 2699, BwaGroup::InterestIncome],
        [2700, 2899, BwaGroup::NeutralIncome],
        [3000, 4099, BwaGroup::Material],
        [4100, 4199, BwaGroup::Personnel],
        [4200, 4299, BwaGroup::Premises],
        [4300, 4359, BwaGroup::OperatingTaxes],
        [4360, 4399, BwaGroup::InsuranceFees],
        [4500, 4599, BwaGroup::Vehicle],
        [4600, 4699, BwaGroup::MarketingTravel],
        [4700, 4799, BwaGroup::GoodsDispatch],
        [4800, 4829, BwaGroup::Repairs],
        [4830, 4899, BwaGroup::Depreciation],
        [4900, 4999, BwaGroup::OtherCosts],
        [8100, 8899, BwaGroup::Revenue],
        [8900, 8959, BwaGroup::OtherOperatingIncome],
        [8960, 8999, BwaGroup::InventoryChange],
    ];

    /** @var list<array{int, int, BwaGroup}> */
    private const SKR04 = [
        [4000, 4799, BwaGroup::Revenue],
        [4800, 4829, BwaGroup::InventoryChange],
        [4830, 4999, BwaGroup::OtherOperatingIncome],
        [5000, 5999, BwaGroup::Material],
        [6000, 6199, BwaGroup::Personnel],
        [6200, 6299, BwaGroup::Depreciation],
        [6300, 6309, BwaGroup::OtherCosts],
        [6310, 6349, BwaGroup::Premises],
        [6350, 6399, BwaGroup::OtherCosts],
        [6400, 6449, BwaGroup::InsuranceFees],
        [6450, 6499, BwaGroup::Repairs],
        [6500, 6599, BwaGroup::Vehicle],
        [6600, 6699, BwaGroup::MarketingTravel],
        [6700, 6799, BwaGroup::GoodsDispatch],
        [6800, 6899, BwaGroup::OtherCosts],
        [6900, 6999, BwaGroup::NeutralExpense],
        [7000, 7199, BwaGroup::InterestIncome],
        [7200, 7299, BwaGroup::NeutralExpense],
        [7300, 7399, BwaGroup::InterestExpense],
        [7400, 7499, BwaGroup::NeutralIncome],
        [7500, 7599, BwaGroup::NeutralExpense],
        [7600, 7649, BwaGroup::IncomeTaxes],
        [7650, 7699, BwaGroup::OperatingTaxes],
    ];

    /**
     * Kontenrahmen aus dem Erlös-Nummernkreis erkennen: SKR03 führt Erlöse
     * unter 8xxx, SKR04 unter 4xxx. Ohne Mehrheit gibt es keinen Rahmen.
     *
     * @param  iterable<AccountingAccount>  $accounts
     */
    public function detectScheme(iterable $accounts): ?string {
        $votes = [self::SCHEME_SKR03 => 0, self::SCHEME_SKR04 => 0];
        foreach ($accounts as $account) {
            if ($account->type !== AccountType::Income) {
                continue;
            }
            $number = $this->leadingNumber($account->number);
            if ($number === null) {
                continue;
            }
            if ($number >= 8000 && $number <= 8999) {
                $votes[self::SCHEME_SKR03]++;
            } elseif ($number >= 4000 && $number <= 4999) {
                $votes[self::SCHEME_SKR04]++;
            }
        }

        if ($votes[self::SCHEME_SKR03] === $votes[self::SCHEME_SKR04]) {
            return null;
        }

        return $votes[self::SCHEME_SKR03] > $votes[self::SCHEME_SKR04] ? self::SCHEME_SKR03 : self::SCHEME_SKR04;
    }

    public function groupFor(AccountingAccount $account, ?string $scheme): ?BwaGroup {
        $explicit = $account->bwa_group;
        if ($explicit instanceof BwaGroup) {
            return $explicit;
        }

        if (in_array($account->euer_category, [EuerCategory::Depreciation, EuerCategory::LowValueAsset], true)) {
            return BwaGroup::Depreciation;
        }

        $number = $this->leadingNumber($account->number);
        if ($number === null || $scheme === null) {
            return null;
        }

        foreach ($scheme === self::SCHEME_SKR03 ? self::SKR03 : self::SKR04 as [$min, $max, $group]) {
            if ($number >= $min && $number <= $max) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Führende vier Ziffern der Kontonummer. Längere DATEV-Nummern (z. B.
     * 84000) erweitern nach rechts — die Gliederung steckt vorn.
     */
    private function leadingNumber(mixed $number): ?int {
        $digits = preg_replace('/\D/', '', (string) $number) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }

        return (int) substr($digits, 0, 4);
    }
}
