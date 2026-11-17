<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskTaxRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Enums\Invoicing\SalesTaxCase;
use App\Exceptions\UnmappableTaxCaseException;
use App\Services\Invoicing\Dto\OutgoingTaxCase;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Steuerfall → Steuerangaben des sevDesk-Rechnungskopfs (MVP-1712). sevDesk-API
 * (openapi.yaml, „sevdesk-Update 2.0 / Tax Rules“ und `Model_Invoice`):
 * Update 2.0 führt `taxRule` — 1 Umsatzsteuerpflichtige Umsätze, 2 Ausfuhren,
 * 3 Innergemeinschaftliche Lieferungen, 4 Steuerfreie Umsätze § 4 UStG,
 * 5 Reverse Charge § 13b UStG (Kz 60), 21 Reverse Charge § 18b UStG (Kz 21),
 * 17 Nicht im Inland steuerbare Leistung, 11 § 19 UStG; außer 1 und 3 nur
 * 0 % je Position. Update 1.0 führt `taxType` default, eu, noteu (Vorgänger
 * von 17) und ss (Vorgänger von 11) — § 13b, § 18b, Ausfuhr und § 4 haben
 * dort keinen Wert.
 */
final class SevDeskTaxRule {
    /** @var array<string, int> Steuerregel (Update 2.0) je Fall außer dem Standardfall */
    private const array RULES = [
        'domestic_reverse_charge' => 5,
        'eu_reverse_charge' => 21,
        'intra_community_supply' => 3,
        'export' => 2,
        'not_taxable' => 17,
        'small_business' => 11,
        'exempt' => 4,
    ];

    /** @var array<string, string> Steuertyp (Update 1.0) je Fall außer dem Standardfall */
    private const array TYPES = [
        'intra_community_supply' => 'eu',
        'not_taxable' => 'noteu',
        'small_business' => 'ss',
    ];

    /**
     * @param  int  $standardRuleId  eingestellte Regel für steuerpflichtige Umsätze (Update 2.0)
     * @param  float  $standardRate  Kopfsatz steuerpflichtiger Rechnungen (Update 1.0)
     * @return array<string, mixed>
     *
     * @throws UnmappableTaxCaseException
     */
    public static function header(OutgoingTaxCase $case, string $bookkeepingVersion, int $standardRuleId, float $standardRate): array {
        if ($bookkeepingVersion === '2.0') {
            return ['taxRule' => ['id' => $case->case === SalesTaxCase::Standard ? $standardRuleId : self::RULES[$case->case->value], 'objectName' => 'TaxRule']];
        }
        if ($case->case === SalesTaxCase::Standard) {
            return [
                'taxType' => 'default',
                'taxRate' => $standardRate,
                'taxText' => (string) __('sevdesk::finance.sevdesk.tax_text', ['rate' => NumberHelper::toUSFormat($standardRate, 2, trimTrailingZeros: true)]),
            ];
        }
        $type = self::TYPES[$case->case->value] ?? throw new UnmappableTaxCaseException((string) __('sevdesk::finance.tax_case.version_one', ['case' => $case->case->label()]));

        return ['taxType' => $type, 'taxRate' => 0.0, 'taxText' => $case->noteOrLabel()];
    }

    /** Grund, warum sevDesk den Fall in dieser Buchhaltungs-Version nicht abbilden kann; null = abbildbar. */
    public static function blocker(OutgoingTaxCase $case, string $bookkeepingVersion): ?string {
        try {
            self::header($case, $bookkeepingVersion, 1, 19.0);
        } catch (UnmappableTaxCaseException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
