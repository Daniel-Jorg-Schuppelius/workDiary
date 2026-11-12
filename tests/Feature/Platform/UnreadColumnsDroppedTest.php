<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnreadColumnsDroppedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Platform\Team;
use App\Models\Project\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623 (gl-135): Spalten ohne Leser fallen weg — `projects.global_activities`,
 * `team_user.is_lead`, `timesheets.attendance_total_minutes`/`untracked_minutes`,
 * `passenger_vehicle_profiles.large_capacity`.
 */
final class UnreadColumnsDroppedTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array COLUMNS = [
        'projects' => ['global_activities'],
        'team_user' => ['is_lead'],
        'timesheets' => ['attendance_total_minutes', 'untracked_minutes'],
        'passenger_vehicle_profiles' => ['large_capacity'],
    ];

    public function test_columns_are_gone_and_a_second_run_changes_nothing(): void {
        $this->assertColumns(false);

        // Wiederholbar: ohne die Spalten tut up() nichts (DDL im Test bräche die Transaktion).
        $migration = require database_path('migrations/2027_04_17_370000_drop_unused_columns_of_projects_teams_timesheets_and_vehicle_profiles.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $this->assertColumns(false);
    }

    public function test_default_project_and_team_lead_work_without_the_columns(): void {
        $this->setUpOrganization();
        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $lead = $this->orgUser();

        $customer->projects()->where('is_default', true)->delete();
        $this->assertInstanceOf(Project::class, $customer->defaultProjectOrCreate());
        $team = Team::factory()->create(['organization_id' => $this->organization->id, 'lead_user_id' => $lead->id]);
        $team->members()->attach($lead->id, ['joined_at' => now()]);
        $this->assertSame([(int) $lead->id], $team->members()->pluck('users.id')->map(static fn ($id): int => (int) $id)->all());
    }

    private function assertColumns(bool $present): void {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame($present, Schema::hasColumn($table, $column), $table . '.' . $column);
            }
        }
    }
}
