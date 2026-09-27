<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapacityPlanningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Team, User};
use App\Services\Hr\CapacityPlanningService;
use App\Services\Hr\EarlyWarnings\CapacityWarningSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-940: Kapazität je Team und Woche gegen geplanten Bedarf. */
final class CapacityPlanningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_capacity_demand_and_warning(): void {
        Carbon::setTestNow('2026-10-05 08:00:00'); // Montag
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Montage']);
        $team->members()->attach($member->id);

        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'assigned_user_id' => $member->id, 'start_at' => CarbonImmutable::parse('2026-10-07 07:00:00'), 'planned_minutes' => 60 * 60]);

        $row = collect(app(CapacityPlanningService::class)->plan($this->organization, 2))->firstWhere('team.id', $team->id);
        $this->assertNotNull($row);
        $first = $row['weeks'][0];
        $this->assertGreaterThan(0, $first['capacity']);
        $this->assertSame(3600, $first['demand']);
        $this->assertGreaterThan(100, $first['utilization']);
        $this->assertSame(0, $row['weeks'][1]['demand']);

        $warnings = app(CapacityWarningSource::class)->warnings($this->organization);
        $this->assertCount(1, $warnings);
        $this->assertSame('capacity', $warnings[0]->kind);

        $this->actingAs($admin)->get(route('teams.capacity'))->assertOk()->assertSee('Montage')->assertSee('60 /');
    }
}
