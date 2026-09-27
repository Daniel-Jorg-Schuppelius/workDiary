<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OffboardingDeputyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Models\Crisis\{CrisisCase, CrisisRole, CrisisTeamAssignment};
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-941: Vertretungen beim Austritt auflösen. */
final class OffboardingDeputyTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    public function test_dialog_lists_and_replaces_deputies(): void {
        $leaving = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Anna Austritt']);
        $colleague = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Bernd Kollege', 'deputy_user_id' => $leaving->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Clara Andere', 'deputy_user_id' => $leaving->id]);
        $successor = User::factory()->user()->create(['organization_id' => $this->org->id, 'name' => 'Dora Nachfolge']);

        $this->actingAs($this->admin)->get(route('org.members.offboard.dialog', $leaving))->assertOk()->assertSee('Bernd Kollege')->assertSee('Clara Andere');

        $this->actingAs($this->admin)->post(route('org.members.offboard', $leaving), [
            'left_at' => now()->addMonth()->toDateString(),
            'deputy_replacements' => [$colleague->sqid => $successor->sqid, $other->sqid => ''],
        ])->assertRedirect();

        $this->assertSame($successor->id, $colleague->fresh()?->deputy_user_id);
        $this->assertNull($other->fresh()?->deputy_user_id);
    }

    public function test_exit_clears_remaining_deputy_references(): void {
        $leaving = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $colleague = User::factory()->user()->create(['organization_id' => $this->org->id, 'deputy_user_id' => $leaving->id]);
        $case = CrisisCase::query()->create(['organization_id' => $this->org->id, 'title' => 'Ausfall', 'category' => 'security', 'severity' => 'critical', 'status' => 'reported', 'created_by' => $this->admin->id]);
        $role = CrisisRole::query()->create(['organization_id' => $this->org->id, 'name' => 'Leitung']);
        $assignment = CrisisTeamAssignment::query()->create(['organization_id' => $this->org->id, 'crisis_case_id' => $case->id, 'crisis_role_id' => $role->id, 'user_id' => $this->admin->id, 'deputy_user_id' => $leaving->id]);

        $this->actingAs($this->admin)->post(route('org.members.offboard', $leaving), ['left_at' => now()->toDateString()])->assertRedirect();

        $this->assertNull($colleague->fresh()?->deputy_user_id);
        $this->assertNull($assignment->fresh()?->deputy_user_id);
    }
}
