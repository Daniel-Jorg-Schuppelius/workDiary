<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidRouteBindingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Models\{Customer, User};
use App\Services\SqidEncoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SqidRouteBindingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_customer_show_works_with_sqid(): void {
        $this->setUpOrganization();
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $user->id,
        ]);

        $sqid = $customer->sqid;
        $this->assertNotEmpty($sqid);
        $this->assertGreaterThanOrEqual(10, strlen($sqid));

        $this->actingAs($user)
            ->get("/customers/{$sqid}")
            ->assertOk();
    }

    public function test_customer_show_with_numeric_id_returns_404(): void {
        $this->setUpOrganization();
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get("/customers/{$customer->id}")
            ->assertNotFound();
    }

    public function test_customer_show_with_cross_model_sqid_returns_404(): void {
        $this->setUpOrganization();
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Sqid eines anderen Modells (User) darf nicht für Customer auflösen.
        $foreignSqid = app(SqidEncoder::class)->encode(User::class, 1);

        $this->actingAs($user)
            ->get("/customers/{$foreignSqid}")
            ->assertNotFound();
    }
}
