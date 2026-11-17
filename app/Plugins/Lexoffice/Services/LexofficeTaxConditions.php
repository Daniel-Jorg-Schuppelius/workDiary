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
     * Buchungskategorien für Belege vom Typ salesinvoice (MVP-1723), Lexware-API-Referenz
     * „Vouchers Endpoint › List of CategoryIds“. Ein Buchhaltungsbeleg kennt nur `taxType`
     * net/gross; den Steuerfall trägt die Kategorie.
     */
    private const array VOUCHER_CATEGORIES = [
        SalesTaxCase::Standard->value => '8f8664a0-fd86-11e1-a21f-0800200c9a66', // Dienstleistungen
        SalesTaxCase::SmallBusiness->value => 'f5c7fee8-f184-4e7a-ab04-8f7e7ad6c207', // Einnahmen als Kleinunternehmer
        SalesTaxCase::EuReverseCharge->value => '380a20cb-d04c-426e-b49c-84c22adfa362', // Fremdleistungen §13b
        SalesTaxCase::IntraCommunitySupply->value => '9075a4e3-66de-4795-a016-3889feca0d20', // Innergemeinschaftliche Lieferung
        SalesTaxCase::Export->value => '93d24c20-ea84-424e-a731-5e1b78d1e6a9', // Ausfuhrlieferungen an Drittländer
        SalesTaxCase::NotTaxable->value => 'ef5b1a6e-f690-4004-9a19-91276348894f', // Dienstleistungen an Drittländer
    ];

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

    /**
     * Buchungskategorie eines Verkaufsbelegs je Steuerfall. Für § 13b im Inland
     * und steuerfreie Umsätze nach § 4 nennt die Referenz keine Kategorie, nicht
     * steuerbare Leistungen gibt es nur an Drittländer.
     *
     * @throws UnmappableTaxCaseException
     */
    public static function voucherCategory(OutgoingTaxCase $case): string {
        if ($case->case === SalesTaxCase::NotTaxable && $case->region !== OutgoingTaxCase::REGION_THIRD_COUNTRY) {
            throw new UnmappableTaxCaseException((string) __('lexoffice::finance.tax_case.not_taxable_outside_third_country'));
        }

        return self::VOUCHER_CATEGORIES[$case->case->value]
            ?? throw new UnmappableTaxCaseException((string) __('lexoffice::finance.tax_case.time_voucher', ['case' => $case->case->label()]));
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
