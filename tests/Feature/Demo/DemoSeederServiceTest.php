<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoSeederServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Models\{Customer, DiaryEntry, OpenIssue, Organization, Project, TimeEntry, User};
use App\Services\Demo\DemoSeederService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DemoSeederServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_seed_marks_organization_as_demo_and_creates_core_records(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $counts = app(DemoSeederService::class)->seed($this->organization->fresh(), $admin);

        $org = Organization::query()->findOrFail($this->organization->id);
        $this->assertTrue($org->is_demo);
        $this->assertNotNull($org->demo_seeded_at);
        $this->assertStringEndsWith('(Demo)', $org->name);

        $this->assertSame(3, $counts['customers']);
        $this->assertSame(5, $counts['projects']);
        $this->assertSame(1, $counts['main_diary_entries']);
        $this->assertSame(3, $counts['time_entries']);
        $this->assertSame(1, $counts['open_issues']);
        $this->assertSame(25, $counts['background_diary_entries']);

        // Konsistenz mit DB. Hinweis: setUpOrganization legt ggf. Default-Projekte
        // an, daher wird hier auf "mindestens" geprüft, nicht auf exakte Zahl.
        $this->assertGreaterThanOrEqual(3, Customer::query()->where('organization_id', $org->id)->count());
        $this->assertGreaterThanOrEqual(5, Project::query()->where('organization_id', $org->id)->count());
        $this->assertSame(26, DiaryEntry::query()->where('organization_id', $org->id)->count());
        $this->assertGreaterThanOrEqual(3, TimeEntry::query()->where('organization_id', $org->id)->count());
    }

    public function test_reset_refuses_for_non_demo_organization(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->expectException(\RuntimeException::class);
        app(DemoSeederService::class)->reset($this->organization->fresh(), $admin);
    }

    public function test_reset_removes_demo_data_and_seeds_again(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $service = app(DemoSeederService::class);
        $service->seed($this->organization->fresh(), $admin);

        $org = $this->organization->fresh();
        $this->assertTrue($org->is_demo);

        $countsAfterReset = $service->reset($org, $admin);

        $this->assertSame(3, $countsAfterReset['customers']);
        $this->assertSame(5, $countsAfterReset['projects']);
        $this->assertSame(26, DiaryEntry::query()->where('organization_id', $org->id)->count());
        $this->assertSame(1, OpenIssue::query()
            ->where('organization_id', $org->id)
            ->where('subject_type', DiaryEntry::class)
            ->count());
    }

    public function test_seed_is_deterministic_in_record_counts_across_runs(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $first = app(DemoSeederService::class)->seed($this->organization->fresh(), $admin);
        $second = app(DemoSeederService::class)->reset($this->organization->fresh(), $admin);

        $this->assertSame($first['customers'], $second['customers']);
        $this->assertSame($first['projects'], $second['projects']);
        $this->assertSame($first['background_diary_entries'], $second['background_diary_entries']);
    }
}
