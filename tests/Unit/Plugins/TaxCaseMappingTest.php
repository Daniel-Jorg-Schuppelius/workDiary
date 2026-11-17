<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxCaseMappingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Enums\Invoicing\SalesTaxCase;
use App\Exceptions\UnmappableTaxCaseException;
use App\Plugins\Easybill\Services\EasybillVatOption;
use App\Plugins\Lexoffice\Services\LexofficeTaxConditions;
use App\Plugins\OrgaMax\Services\OrgaMaxTaxCase;
use App\Plugins\SevDesk\Services\SevDeskTaxRule;
use App\Services\Invoicing\Dto\OutgoingTaxCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MVP-1712: Steuerfall → Steuerart je Faktura-/Buchhaltungsprogramm, gegen die
 * Werte der API-Dokumentation (Lexware-API-Referenz „Tax Conditions Details“,
 * sevDesk openapi.yaml „Tax Rules“, easybill swagger.json `Document.vat_option`,
 * orgaMAX OpenAPI ohne Steuerart). Nicht abbildbare Fälle sperren mit Grund.
 */
final class TaxCaseMappingTest extends TestCase {
    /**
     * Fall → [Lexware taxType, sevDesk taxRule (Update 2.0), sevDesk taxType (Update 1.0, null = gesperrt), easybill vat_option, orgaMAX abbildbar].
     *
     * @return iterable<string, array{0: OutgoingTaxCase, 1: string, 2: int, 3: ?string, 4: ?string, 5: bool}>
     */
    public static function cases(): iterable {
        $eu = OutgoingTaxCase::REGION_EU;
        $third = OutgoingTaxCase::REGION_THIRD_COUNTRY;

        yield 'S Inland' => [new OutgoingTaxCase(SalesTaxCase::Standard), 'net', 1, 'default', null, true];
        yield 'AE EU-Unternehmen' => [new OutgoingTaxCase(SalesTaxCase::EuReverseCharge, 'Reverse Charge', $eu, true), 'externalService13b', 21, null, 'nStbUstID', false];
        yield '§ 13b Inland' => [new OutgoingTaxCase(SalesTaxCase::DomesticReverseCharge, '§ 13b UStG'), 'constructionService13b', 5, null, 'revc', false];
        yield 'K innergemeinschaftliche Lieferung' => [new OutgoingTaxCase(SalesTaxCase::IntraCommunitySupply, null, $eu, true), 'intraCommunitySupply', 3, 'eu', 'IG', false];
        yield 'G Ausfuhr' => [new OutgoingTaxCase(SalesTaxCase::Export, 'Ausfuhr', $third), 'thirdPartyCountryDelivery', 2, null, 'AL', false];
        yield 'O Drittland-Leistung' => [new OutgoingTaxCase(SalesTaxCase::NotTaxable, '§ 3a', $third), 'thirdPartyCountryService', 17, 'noteu', 'nStb', false];
        yield 'O Katalogleistung Verbraucher Drittland' => [new OutgoingTaxCase(SalesTaxCase::NotTaxable, '§ 3a Abs. 4', $third, false), 'thirdPartyCountryService', 17, 'noteu', 'nStb', false];
        yield 'E § 19' => [new OutgoingTaxCase(SalesTaxCase::SmallBusiness, '§ 19 UStG'), 'vatfree', 11, 'ss', 'smallBusiness', true];
        yield 'E § 4' => [new OutgoingTaxCase(SalesTaxCase::Exempt), 'vatfree', 4, null, 'sStfr', false];
    }

    #[DataProvider('cases')]
    public function test_lexoffice_tax_type(OutgoingTaxCase $case, string $taxType, int $rule, ?string $legacyType, ?string $vatOption, bool $orgaMax): void {
        $conditions = LexofficeTaxConditions::for($case, 'net');

        $this->assertSame($taxType, $conditions['taxType']);
        // Steuerfreie Arten tragen den Hinweis als taxTypeNote; der Standardfall bleibt wie bisher ohne.
        $this->assertSame($case->zeroRated() ? $case->noteOrLabel() : null, $conditions['taxTypeNote'] ?? null);
        $this->assertNull(LexofficeTaxConditions::blocker($case));
        $this->assertSame(! in_array($taxType, ['net', 'gross', 'vatfree'], true), LexofficeTaxConditions::requiresContact($taxType));
    }

    #[DataProvider('cases')]
    public function test_sevdesk_tax_rule_in_update_two(OutgoingTaxCase $case, string $taxType, int $rule, ?string $legacyType, ?string $vatOption, bool $orgaMax): void {
        $this->assertSame(['taxRule' => ['id' => $rule, 'objectName' => 'TaxRule']], SevDeskTaxRule::header($case, '2.0', 1, 19.0));
        $this->assertNull(SevDeskTaxRule::blocker($case, '2.0'));
    }

    #[DataProvider('cases')]
    public function test_sevdesk_tax_type_in_update_one(OutgoingTaxCase $case, string $taxType, int $rule, ?string $legacyType, ?string $vatOption, bool $orgaMax): void {
        if ($legacyType === null) {
            $this->assertNotNull(SevDeskTaxRule::blocker($case, '1.0'));
            $this->expectException(UnmappableTaxCaseException::class);
            SevDeskTaxRule::header($case, '1.0', 1, 19.0);

            return;
        }

        $header = SevDeskTaxRule::header($case, '1.0', 1, 19.0);
        $this->assertSame($legacyType, $header['taxType']);
        $this->assertSame($case->zeroRated() ? 0.0 : 19.0, $header['taxRate']);
    }

    #[DataProvider('cases')]
    public function test_easybill_vat_option(OutgoingTaxCase $case, string $taxType, int $rule, ?string $legacyType, ?string $vatOption, bool $orgaMax): void {
        $this->assertSame($vatOption, EasybillVatOption::for($case));
    }

    #[DataProvider('cases')]
    public function test_orgamax_maps_only_standard_and_small_business(OutgoingTaxCase $case, string $taxType, int $rule, ?string $legacyType, ?string $vatOption, bool $orgaMax): void {
        $this->assertSame($orgaMax, OrgaMaxTaxCase::blocker($case) === null);
    }

    /** Gegenprobe: Der Standardfall nimmt die Voreinstellung bzw. die eingestellte Steuerregel wie bisher. */
    public function test_standard_case_keeps_the_configured_values(): void {
        $standard = new OutgoingTaxCase(SalesTaxCase::Standard);

        $this->assertSame(['taxType' => 'gross'], LexofficeTaxConditions::for($standard, 'gross'));
        $this->assertSame(['taxType' => 'vatfree'], LexofficeTaxConditions::for($standard, 'vatfree'));
        $this->assertSame(['taxRule' => ['id' => 7, 'objectName' => 'TaxRule']], SevDeskTaxRule::header($standard, '2.0', 7, 19.0));
        $this->assertSame('default', SevDeskTaxRule::header($standard, '1.0', 1, 7.0)['taxType']);
        $this->assertSame(7.0, SevDeskTaxRule::header($standard, '1.0', 1, 7.0)['taxRate']);
        $this->assertNull(EasybillVatOption::for($standard));
    }

    /** Nicht steuerbar mit Leistungsort in der EU (Grundstück): Lexware kennt nur Drittländer, easybill unterscheidet nach USt-IdNr. */
    public function test_not_taxable_in_the_eu(): void {
        $withVatId = new OutgoingTaxCase(SalesTaxCase::NotTaxable, null, OutgoingTaxCase::REGION_EU, true);
        $withoutVatId = new OutgoingTaxCase(SalesTaxCase::NotTaxable, null, OutgoingTaxCase::REGION_EU, false);

        $this->assertSame((string) __('lexoffice::finance.tax_case.not_taxable_outside_third_country'), LexofficeTaxConditions::blocker($withVatId));
        $this->assertSame('nStbUstID', EasybillVatOption::for($withVatId));
        $this->assertSame('nStbNoneUstID', EasybillVatOption::for($withoutVatId));
        $this->assertSame(['taxRule' => ['id' => 17, 'objectName' => 'TaxRule']], SevDeskTaxRule::header($withVatId, '2.0', 1, 19.0));
    }

    /** Leistungsorte in mehreren Gebieten: Lexware und easybill brauchen genau eine Steuerart. */
    public function test_not_taxable_in_several_regions_is_blocked(): void {
        $open = new OutgoingTaxCase(SalesTaxCase::NotTaxable, null, null);

        $this->assertNotNull(LexofficeTaxConditions::blocker($open));
        $this->assertSame((string) __('easybill::finance.tax_case.not_taxable_region'), EasybillVatOption::blocker($open));
        $this->expectException(UnmappableTaxCaseException::class);
        EasybillVatOption::for($open);
    }

    public function test_orgamax_names_the_case_it_cannot_take(): void {
        $case = new OutgoingTaxCase(SalesTaxCase::EuReverseCharge);

        $this->assertSame((string) __('orgamax::orgamax.tax_case.unmappable', ['case' => SalesTaxCase::EuReverseCharge->label()]), OrgaMaxTaxCase::blocker($case));
        $this->expectException(UnmappableTaxCaseException::class);
        OrgaMaxTaxCase::assertMappable($case);
    }
}
