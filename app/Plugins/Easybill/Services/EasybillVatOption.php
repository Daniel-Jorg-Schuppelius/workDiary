<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillVatOption.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Invoicing\SalesTaxCase;
use App\Exceptions\UnmappableTaxCaseException;
use App\Services\Invoicing\Dto\OutgoingTaxCase;

/**
 * Steuerfall → easybill-`vat_option` des Belegs (MVP-1712). easybill REST API
 * (swagger.json, Definition `Document`): NULL normal steuerbar, nStb nicht
 * steuerbar (Drittland), nStbUstID nicht steuerbar (EU mit USt-IdNr.),
 * nStbNoneUstID nicht steuerbar (EU ohne USt-IdNr.), revc Steuerschuldwechsel
 * § 13b (Inland), IG innergemeinschaftliche Lieferung, AL Ausfuhrlieferung,
 * sStfr sonstige Steuerbefreiung, smallBusiness Kleinunternehmen.
 * Der Standardfall sendet kein Feld, wie bisher.
 */
final class EasybillVatOption {
    /** @throws UnmappableTaxCaseException */
    public static function for(OutgoingTaxCase $case): ?string {
        return match ($case->case) {
            SalesTaxCase::Standard => null,
            SalesTaxCase::DomesticReverseCharge => 'revc',
            // Die sonstige Leistung an EU-Unternehmer ist in Deutschland nicht steuerbar; der Empfänger schuldet die Steuer.
            SalesTaxCase::EuReverseCharge => 'nStbUstID',
            SalesTaxCase::IntraCommunitySupply => 'IG',
            SalesTaxCase::Export => 'AL',
            SalesTaxCase::NotTaxable => match ($case->region) {
                OutgoingTaxCase::REGION_THIRD_COUNTRY => 'nStb',
                OutgoingTaxCase::REGION_EU => $case->buyerHasVatId ? 'nStbUstID' : 'nStbNoneUstID',
                default => throw new UnmappableTaxCaseException((string) __('easybill::finance.tax_case.not_taxable_region')),
            },
            SalesTaxCase::SmallBusiness => 'smallBusiness',
            SalesTaxCase::Exempt => 'sStfr',
        };
    }

    /** Grund, warum easybill den Fall nicht abbilden kann; null = abbildbar. */
    public static function blocker(OutgoingTaxCase $case): ?string {
        try {
            self::for($case);
        } catch (UnmappableTaxCaseException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
