<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExportPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Policies\GobdExportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132): Menü UND Download
 * hängen ausschließlich am Recht finance.gobd.export — kein sonstiges
 * Finanzrecht genügt.
 */
final class GobdExportPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private GobdExportPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new GobdExportPolicy;
    }

    public function test_gobd_export_permission_grants_page_and_download(): void {
        $exporter = $this->actorIn($this->organization, [P::FinanceGobdExport]);

        $this->assertTrue($this->policy->viewAny($exporter));
        $this->assertTrue($this->policy->export($exporter));
    }

    public function test_other_finance_rights_do_not_grant_gobd_export(): void {
        $finance = $this->actorIn($this->organization, [P::FinanceViewAny, P::FinanceConfig]);

        $this->assertFalse($this->policy->viewAny($finance));
        $this->assertFalse($this->policy->export($finance));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->export($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->export($this->orglessActor()));
    }
}
