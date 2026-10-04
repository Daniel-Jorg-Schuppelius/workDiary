<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReservedCustomerSlugTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Models\Customer\Customer;
use App\Models\Project\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Route};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Projekt-URL lautet "<kunde>/<projekt>". Kürzel, die dort schon etwas
 * bedeuten — `intern` für Projekte ohne Kunden, feste Pfade unter /projects —
 * darf kein Kunde tragen: ein Kunde „Intern" öffnete sonst fremde interne
 * Projekte (Laufzeitsonde 2026-10-04).
 */
class ReservedCustomerSlugTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_customer_named_like_the_placeholder_gets_a_free_slug(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Intern', 'slug' => null]);
        $this->assertNotContains($customer->fresh()->slug, Customer::RESERVED_SLUGS);

        $internal = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => null, 'name' => 'Umzug', 'slug' => null]);
        $ofCustomer = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Umzug', 'slug' => null]);

        $this->assertNotSame($internal->fresh()->getRouteKey(), $ofCustomer->fresh()->getRouteKey());
        $this->assertSame($ofCustomer->id, (new Project())->resolveRouteBinding($ofCustomer->fresh()->getRouteKey())?->id);
        $this->assertSame($internal->id, (new Project())->resolveRouteBinding($internal->fresh()->getRouteKey())?->id);
    }

    /** Neue feste Pfade unter /projects müssen in die Liste, sonst verdecken sie Projekte eines gleichnamigen Kunden. */
    public function test_static_paths_under_projects_are_reserved(): void {
        $segments = [];
        foreach (Route::getRoutes() as $route) {
            $parts = explode('/', $route->uri());
            if ($parts[0] === 'projects' && isset($parts[1]) && ! str_starts_with($parts[1], '{')) {
                $segments[$parts[1]] = true;
            }
        }

        $this->assertNotEmpty($segments);
        $this->assertContains('intern', Customer::RESERVED_SLUGS);
        $this->assertSame([], array_values(array_diff(array_keys($segments), Customer::RESERVED_SLUGS)), 'Fester Pfad unter /projects fehlt in Customer::RESERVED_SLUGS.');
    }

    public function test_migration_frees_existing_reserved_slugs(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Altbestand']);
        $taken = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Belegt']);
        DB::table('customers')->where('id', $customer->id)->update(['slug' => 'intern']);
        DB::table('customers')->where('id', $taken->id)->update(['slug' => 'intern-2']);

        (require database_path('migrations/2027_03_09_150000_reslug_customers_with_reserved_slugs.php'))->up();

        $this->assertSame('intern-3', DB::table('customers')->where('id', $customer->id)->value('slug'));
        $this->assertSame('intern-2', DB::table('customers')->where('id', $taken->id)->value('slug'));
    }
}
