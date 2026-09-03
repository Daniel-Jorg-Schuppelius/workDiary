<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsEntitlements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\{MarketplaceCompany, MarketplaceEntitlement};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

trait BuildsEntitlements {
    private static int $sequence = 0;

    private function entitlement(string $edition, string $fee, string $startsOn = '2024-08-02', ?string $endsOn = '2026-08-02', BillingFrequency $frequency = BillingFrequency::Yearly, string $company = 'Muster Bau GmbH', string $source = MarketplaceEntitlement::SOURCE_TELEKOM, ?int $quantity = null): MarketplaceEntitlement {
        self::$sequence++;

        return new MarketplaceEntitlement(
            company: new MarketplaceCompany(($source === MarketplaceEntitlement::SOURCE_QUALITYHOSTING ? 'CNL-' : 'TK-') . MarketplaceCompany::normalizeName($company), $company, null, null),
            entitlementId: ($source === MarketplaceEntitlement::SOURCE_QUALITYHOSTING ? 'CNLCON' : 'ent-') . self::$sequence,
            orderId: 'order-' . self::$sequence,
            application: 'Microsoft 365 Business',
            edition: $edition,
            fee: Money::of($fee, CurrencyCode::Euro),
            frequency: $frequency,
            startsOn: CarbonImmutable::parse($startsOn),
            endsOn: $endsOn === null ? null : CarbonImmutable::parse($endsOn),
            status: 'ACTIVE',
            assignedUsers: 0,
            sourceLine: self::$sequence + 1,
            source: $source,
            quantity: $quantity,
            unitFee: $quantity !== null && $quantity > 0 ? Money::of($fee, CurrencyCode::Euro)->dividedBy($quantity) : null,
        );
    }
}
