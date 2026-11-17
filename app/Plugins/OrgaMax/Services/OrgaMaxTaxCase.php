<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxTaxCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Enums\Invoicing\SalesTaxCase;
use App\Exceptions\UnmappableTaxCaseException;
use App\Services\Invoicing\Dto\OutgoingTaxCase;

/**
 * Steuerfall und orgaMAX (MVP-1712). Die veröffentlichte API (OpenAPI vom
 * 12.10.2026: `OrderCreateData`, `OfferCreateData`, `CustomerDataObject`,
 * `InvoiceDataObject`) führt keine Steuerart, nur `vatPercent` je Position;
 * `smallBusiness` am Auftrag ist ein Lesefeld des Kontos. Ein Satz von 0 %
 * allein wäre in orgaMAX ein steuerpflichtiger Umsatz zu 0 % ohne Grund und
 * Kennziffer. Abbildbar sind deshalb nur der Standardfall und § 19.
 */
final class OrgaMaxTaxCase {
    /** @throws UnmappableTaxCaseException */
    public static function assertMappable(OutgoingTaxCase $case): void {
        $reason = self::blocker($case);
        if ($reason !== null) {
            throw new UnmappableTaxCaseException($reason);
        }
    }

    /** Grund, warum orgaMAX den Fall nicht abbilden kann; null = abbildbar. */
    public static function blocker(OutgoingTaxCase $case): ?string {
        return in_array($case->case, [SalesTaxCase::Standard, SalesTaxCase::SmallBusiness], true)
            ? null
            : (string) __('orgamax::orgamax.tax_case.unmappable', ['case' => $case->case->label()]);
    }
}
