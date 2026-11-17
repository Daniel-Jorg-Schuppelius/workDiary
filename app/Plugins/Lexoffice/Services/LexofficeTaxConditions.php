<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTaxConditions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Invoicing\SalesTaxCase;
use App\Exceptions\UnmappableTaxCaseException;
use App\Services\Invoicing\Dto\OutgoingTaxCase;

/**
 * Steuerfall → Lexware-`taxConditions` von Rechnung und Rechnungskorrektur
 * (MVP-1712). Lexware-API-Referenz, „Tax Conditions Details“ und „Unit Price
 * Details“: `taxType` net, gross, vatfree, intraCommunitySupply,
 * constructionService13b, externalService13b, thirdPartyCountryService,
 * thirdPartyCountryDelivery, photovoltaicEquipment; steuerfreie Arten mit
 * Satz 0 je Position und optionaler `taxTypeNote` (ohne sie der Standardtext
 * der Organisation); alle außer vatfree nur mit referenziertem Kontakt.
 * Nicht steuerbare Leistungen außerhalb des Drittlands (Grundstück in der EU)
 * kennt Lexware nicht.
 */
final class LexofficeTaxConditions {
    /** Arten, die laut Referenz auch ohne referenzierten Kontakt gehen. */
    private const array WITHOUT_CONTACT = ['net', 'gross', 'vatfree'];

    /**
     * @param  string  $standardType  Steuerart steuerpflichtiger Belege (Voreinstellung bzw. `vatfree` ohne steuerpflichtige Zeile)
     * @return array{taxType: string, taxTypeNote?: string}
     *
     * @throws UnmappableTaxCaseException
     */
    public static function for(OutgoingTaxCase $case, string $standardType): array {
        $type = match ($case->case) {
            SalesTaxCase::Standard => null,
            SalesTaxCase::SmallBusiness, SalesTaxCase::Exempt => 'vatfree',
            // Lexware kennt für § 13b im Inland nur „Bauleistungen gem. § 13b UStG“.
            SalesTaxCase::DomesticReverseCharge => 'constructionService13b',
            SalesTaxCase::EuReverseCharge => 'externalService13b',
            SalesTaxCase::IntraCommunitySupply => 'intraCommunitySupply',
            SalesTaxCase::Export => 'thirdPartyCountryDelivery',
            SalesTaxCase::NotTaxable => $case->region === OutgoingTaxCase::REGION_THIRD_COUNTRY
                ? 'thirdPartyCountryService'
                : throw new UnmappableTaxCaseException((string) __('lexoffice::finance.tax_case.not_taxable_outside_third_country')),
        };

        return $type === null ? ['taxType' => $standardType] : ['taxType' => $type, 'taxTypeNote' => $case->noteOrLabel()];
    }

    /** Verlangt die Steuerart einen in Lexware angelegten Kontakt statt einer Anschrift im Beleg? */
    public static function requiresContact(string $taxType): bool {
        return ! in_array($taxType, self::WITHOUT_CONTACT, true);
    }

    /** Grund, warum Lexware den Fall nicht abbilden kann; null = abbildbar. */
    public static function blocker(OutgoingTaxCase $case): ?string {
        try {
            self::for($case, 'net');
        } catch (UnmappableTaxCaseException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
