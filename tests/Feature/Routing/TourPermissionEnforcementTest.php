<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Enums\User\UserRole;
use App\Models\Diary\Tour;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Touren anderer Fahrer planen und einsehen verlangt tour.manage (lk-06);
 * eigene Touren bleiben beim Fahrer.
 */
class TourPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_foreign_tours_need_tour_manage(): void {
        $driver = $this->userWithRole(UserRole::Aussendienst->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $colleague = $this->userWithRole(UserRole::Aussendienst->value);
        $tour = $this->tourOf($driver);

        $this->assertTrue(Gate::forUser($driver)->allows('update', $tour));
        $this->assertTrue(Gate::forUser($lead)->allows('update', $tour));
        $this->assertFalse(Gate::forUser($colleague)->allows('update', $tour));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('delete', $tour));

        $this->actingAs($lead)->get(route('tours.index', ['user' => $driver->sqid]))->assertOk();
        $this->actingAs($colleague)->get(route('tours.index', ['user' => $driver->sqid]))->assertForbidden();
    }

    private function tourOf(User $driver): Tour {
        return Tour::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $driver->id]);
    }
}
