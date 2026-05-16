<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FlexCalculatorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Flex',
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->user->id,
        ]);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'frame_start' => '06:00',
            'frame_end' => '20:00',
            'core_start' => '09:00',
            'core_end' => '15:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2030-01-01',
        ]);
    }

    public function test_overtime_is_positive_balance(): void {
        // 2030-01-02 = Mittwoch
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-02',
            'minutes' => 540, // 9h
            'kind' => TimeEntry::KIND_WORK,
        ]);

        $calc = app(FlexCalculator::class);
        $b = $calc->dailyBalance($this->user, CarbonImmutable::parse('2030-01-02'));

        $this->assertSame(480, $b['target']);
        $this->assertSame(540, $b['actual']);
        $this->assertSame(60, $b['balance']);
    }

    public function test_weekend_target_is_zero(): void {
        // 2030-01-05 = Samstag
        $calc = app(FlexCalculator::class);
        $b = $calc->dailyBalance($this->user, CarbonImmutable::parse('2030-01-05'));
        $this->assertSame(0, $b['target']);
        $this->assertSame(0, $b['actual']);
    }

    public function test_monthly_balance_aggregates(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-03-04', // Montag
            'minutes' => 480,
            'kind' => TimeEntry::KIND_WORK,
        ]);
        $calc = app(FlexCalculator::class);
        $m = $calc->monthlyBalance($this->user, 2030, 3);
        $this->assertSame(480, $m['days']['2030-03-04']['actual']);
        $this->assertGreaterThan(0, $m['target']);
    }
}
