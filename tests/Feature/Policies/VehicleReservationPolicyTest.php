<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Organization, User, VehicleReservation};
use App\Policies\VehicleReservationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Fahrzeugreservierungen: Lesen mit vehicle.viewAny ODER vehicle.reserve,
 * Anlegen mit vehicle.reserve, Löschen für den Reservierenden oder
 * Reservierungsberechtigte — alles hart organisationsgebunden.
 */
final class VehicleReservationPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private VehicleReservationPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new VehicleReservationPolicy;
    }

    private function reservation(User $reserver, ?int $orgId = null): VehicleReservation {
        $reservation = new VehicleReservation;
        $reservation->organization_id = $orgId ?? $this->organization->id;
        $reservation->reserved_by_user_id = $reserver->id;

        return $reservation;
    }

    public function test_reserver_creates_views_and_deletes_in_own_org(): void {
        $reserver = $this->actorIn($this->organization, [P::VehicleReserve]);
        $viewer = $this->actorIn($this->organization, [P::VehicleViewAny]);
        $reservation = $this->reservation($reserver);

        $this->assertTrue($this->policy->viewAny($reserver));
        $this->assertTrue($this->policy->create($reserver));
        $this->assertTrue($this->policy->view($reserver, $reservation));
        $this->assertTrue($this->policy->delete($reserver, $reservation));

        $this->assertTrue($this->policy->view($viewer, $reservation));
        $this->assertFalse($this->policy->create($viewer), 'Nur-Leser reserviert nicht.');
        $this->assertFalse($this->policy->delete($viewer, $reservation), 'Nur-Leser löscht keine fremden Reservierungen.');
    }

    public function test_foreign_org_is_denied_even_with_permissions(): void {
        $reserver = $this->actorIn($this->organization, [P::VehicleReserve]);
        $reservation = $this->reservation($reserver);

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::VehicleViewAny, P::VehicleReserve]);
        $this->actAsTeam($foreignOrg);

        $this->assertFalse($this->policy->view($attacker, $reservation));
        $this->assertFalse($this->policy->delete($attacker, $reservation));
    }

    public function test_permissionless_or_orgless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
