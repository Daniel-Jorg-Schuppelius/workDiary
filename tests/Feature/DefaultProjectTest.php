<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DefaultProjectTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DefaultProjectTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_customer_creation_provisions_default_project(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertSame(1, $customer->projects()->count());
        $default = $customer->defaultProject();
        $this->assertNotNull($default);
        $this->assertTrue($default->is_default);
        $this->assertSame((int) $this->organization->id, (int) $default->organization_id);
    }

    public function test_default_project_or_create_returns_existing(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $first = $customer->defaultProjectOrCreate();
        $second = $customer->defaultProjectOrCreate();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $customer->projects()->count());
    }

    public function test_saving_is_default_unsets_others_for_same_customer(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $original = $customer->defaultProject();
        $this->assertNotNull($original);

        $other = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Wartung Spezial',
            'status' => ProjectStatus::Active->value,
            'is_default' => true,
        ]);

        $this->assertTrue($other->fresh()->is_default);
        $this->assertFalse($original->fresh()->is_default);
        $this->assertSame(1, $customer->projects()->where('is_default', true)->count());
    }

    public function test_quick_store_uses_customer_default_project(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('timesheets.quick'), [
            'customer_id' => $customer->id,
        ]);

        $default = $customer->defaultProject();
        $this->assertNotNull($default);
        $timesheet = Timesheet::query()->where('project_id', $default->id)->first();
        $this->assertNotNull($timesheet);
        $response->assertRedirect(route('projects.timesheets.show', [$default, $timesheet]));
    }

    public function test_quick_store_with_explicit_project_overrides_default(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $explicit = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Webshop Relaunch',
            'status' => ProjectStatus::Active->value,
        ]);

        $response = $this->actingAs($this->user)->post(route('timesheets.quick'), [
            'customer_id' => $customer->id,
            'project_id' => $explicit->id,
            'work_date' => '2026-05-10',
        ]);

        $timesheet = Timesheet::query()->where('project_id', $explicit->id)->first();
        $this->assertNotNull($timesheet);
        $this->assertSame('2026-05-10', $timesheet->work_date->toDateString());
        $response->assertRedirect(route('projects.timesheets.show', [$explicit, $timesheet]));
    }

    public function test_customer_destroy_cleans_up_default_project_only(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        // hat nur das auto-erzeugte Standardprojekt → l\u00f6schen erlaubt
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('projects', ['customer_id' => $customer->id]);
    }
}
