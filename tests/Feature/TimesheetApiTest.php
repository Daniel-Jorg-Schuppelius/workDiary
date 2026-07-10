<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TimesheetApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'API',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_and_list_timesheet_via_api(): void {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson(route('api.timesheets.store', $this->project), [
            'work_date' => '2030-04-01',
            'customer_name' => 'API Kunde',
        ])->assertCreated()
            ->assertJsonPath('data.work_date', '2030-04-01')
            ->assertJsonPath('data.status', TimesheetStatus::Draft->value);

        $this->getJson(route('api.timesheets.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_request_is_rejected(): void {
        $this->getJson(route('api.timesheets.index'))->assertUnauthorized();
    }
}
