<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnitPriceCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Services\Reselling\Marketplace\UnitPriceCatalog;
use Tests\TestCase;

class UnitPriceCatalogTest extends TestCase {
    use BuildsEntitlements;

    public function test_derives_quantities_from_multiples_of_known_unit_prices(): void {
        $entitlements = [
            $this->entitlement('Microsoft 365 Business Premium', '1958.07'),
            $this->entitlement('Microsoft 365 Business Premium', '244.76'),
            $this->entitlement('Microsoft 365 Business Premium', '489.52'),
            $this->entitlement('Microsoft 365 Business Premium', '226.91'),
            $this->entitlement('Microsoft 365 Business Premium', '453.82'),
            $this->entitlement('Microsoft 365 Business Standard', '138.94'),
            $this->entitlement('Microsoft 365 Business Standard', '555.78'),
            $this->entitlement('Microsoft 365 Business Standard', '128.38'),
            $this->entitlement('Microsoft 365 Business Standard', '385.13'),
            $this->entitlement('Microsoft 365 Business Standard', '167.08'),
        ];

        $catalog = UnitPriceCatalog::fromEntitlements($entitlements);

        $this->assertSame(8, $catalog->quantityOf($entitlements[0]));
        $this->assertSame(24476, $catalog->unitPriceOf($entitlements[0])->getMinorAmount());
        $this->assertSame(1, $catalog->quantityOf($entitlements[1]));
        $this->assertSame(2, $catalog->quantityOf($entitlements[2]));
        $this->assertSame(1, $catalog->quantityOf($entitlements[3]));
        $this->assertSame(2, $catalog->quantityOf($entitlements[4]));
        $this->assertSame(22691, $catalog->unitPriceOf($entitlements[4])->getMinorAmount());

        $this->assertSame(4, $catalog->quantityOf($entitlements[6]), '555,78 = 4 × 138,94 (Rundungstoleranz 2 ct je Stück)');
        $this->assertSame(3, $catalog->quantityOf($entitlements[8]), '385,13 = 3 × 128,38');
        $this->assertSame(1, $catalog->quantityOf($entitlements[9]), '167,08 ist kein Vielfaches — eigener Stückpreis');

        $units = $catalog->unitPrices();
        $this->assertSame([22691, 24476], array_map(static fn($m) => $m->getMinorAmount(), $units['Microsoft 365 Business Premium']));
        $this->assertSame([12838, 13894, 16708], array_map(static fn($m) => $m->getMinorAmount(), $units['Microsoft 365 Business Standard']));
    }

    public function test_unknown_entitlement_falls_back_to_its_own_fee(): void {
        $known = $this->entitlement('Exchange Online (Plan 1)', '43.98');
        $catalog = UnitPriceCatalog::fromEntitlements([$known]);

        $other = $this->entitlement('Microsoft Teams Essentials', '83.11');
        $this->assertSame(1, $catalog->quantityOf($other));
        $this->assertSame(8311, $catalog->unitPriceOf($other)->getMinorAmount());
    }
}
