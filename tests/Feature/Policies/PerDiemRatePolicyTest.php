<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRatePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\{PerDiemRate, User};
use App\Policies\PerDiemRatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Verpflegungspauschalen sind GLOBALE, mandantenübergreifende Stammdaten:
 * Schreibzugriff ändert die Reisekostenberechnung ALLER Mandanten und ist
 * deshalb dem Plattform-Betreiber (isGlobalAdmin) vorbehalten — der frühere
 * isAdmin()-Bypass war ein Cross-Tenant-Loch (siehe Policy-Docblock).
 * Lesen dürfen alle (Reisekosten-Berechnung).
 */
final class PerDiemRatePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private PerDiemRatePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new PerDiemRatePolicy;
    }

    public function test_everyone_may_read_rates(): void {
        $user = $this->actorIn($this->organization);
        $rate = new PerDiemRate;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $rate));
    }

    public function test_org_admin_must_not_write_global_rates(): void {
        // Fixiert den geschlossenen Cross-Tenant-Befund: org-lokaler Admin
        // darf globale Pauschalen NICHT schreiben.
        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $rate = new PerDiemRate;

        $gate = Gate::forUser($orgAdmin);
        $this->assertTrue($gate->denies('create', PerDiemRate::class));
        $this->assertTrue($gate->denies('update', $rate));
        $this->assertTrue($gate->denies('delete', $rate));
        $this->assertTrue($gate->allows('viewAny', PerDiemRate::class));
    }

    public function test_platform_admin_writes_via_global_bypass(): void {
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $rate = new PerDiemRate;

        $gate = Gate::forUser($platformAdmin);
        $this->assertTrue($gate->allows('create', PerDiemRate::class));
        $this->assertTrue($gate->allows('update', $rate));
        $this->assertTrue($gate->allows('delete', $rate));
    }

    public function test_regular_user_cannot_write(): void {
        $user = $this->actorIn($this->organization);
        $rate = new PerDiemRate;

        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $rate));
        $this->assertFalse($this->policy->delete($user, $rate));
    }
}
