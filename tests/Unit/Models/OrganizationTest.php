<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Models;

use App\Models\Organization;
use Tests\TestCase;

final class OrganizationTest extends TestCase {
    public function test_plan_labels_are_localized(): void {
        app()->setLocale('de');

        $this->assertSame('Kostenlos', Organization::planLabel(Organization::PLAN_FREE));
        $this->assertSame('Pro', Organization::planLabel(Organization::PLAN_PRO));
        $this->assertSame('Enterprise', Organization::planLabel(Organization::PLAN_ENTERPRISE));
    }
}
