<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\TimeEntry\TimeEntryActivityType;
use App\Models\{IntegrationInboxItem, Project, TimeEntry, User};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\ProjectTimeSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectTimeSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
    }

    private function spec(): ProjectTimeSpec {
        return app(ProjectTimeSpec::class);
    }

    private function seedUser(string $email = 'worker@example.com'): User {
        return User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => $email,
        ]);
    }

    public function test_matched_project_books_time_entry(): void {
        $this->seedUser();
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Website Relaunch',
        ]);

        $row = $this->spec()->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'project' => 'website relaunch',
            'description' => 'Konzeption',
        ]);

        [$outcome, $issue] = $this->spec()->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(TimeEntryActivityType::Project, $entry->activity_type);
        $this->assertSame(120, $entry->minutes);
    }

    public function test_unmatched_project_is_staged_not_blind_created(): void {
        $this->seedUser();

        $row = $this->spec()->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'project' => 'Unbekanntes Projekt',
        ]);

        [$outcome] = $this->spec()->upsertOrStage($row, $this->organization);

        $this->assertSame(ImportOutcome::Skipped, $outcome);
        // Kein Blind-Projekt, keine Buchung.
        $this->assertSame(0, Project::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(0, TimeEntry::query()->where('organization_id', $this->organization->id)->count());
        // Aber in der Zuordnungs-Inbox.
        $this->assertSame(1, IntegrationInboxItem::query()
            ->where('organization_id', $this->organization->id)
            ->where('external_type', 'project-time')
            ->count());
    }

    public function test_ambiguous_project_name_is_staged(): void {
        $this->seedUser();
        Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Support']);
        Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Support']);

        $row = $this->spec()->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'project' => 'Support',
        ]);

        [$outcome] = $this->spec()->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Skipped, $outcome);
        $this->assertSame(0, TimeEntry::query()->where('organization_id', $this->organization->id)->count());
    }

    public function test_reimport_with_external_id_updates_not_duplicates(): void {
        $this->seedUser();
        Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Website Relaunch']);

        $base = [
            'user_email' => 'worker@example.com',
            'date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'project' => 'Website Relaunch',
            'external_id' => 'toggl-99',
        ];

        [$first] = $this->spec()->upsert($this->spec()->normalize($base), $this->organization);
        [$second] = $this->spec()->upsert($this->spec()->normalize(['end_time' => '12:00'] + $base), $this->organization);

        $this->assertSame(ImportOutcome::Created, $first);
        $this->assertSame(ImportOutcome::Updated, $second);
        $this->assertSame(1, TimeEntry::query()->where('organization_id', $this->organization->id)->count());
    }
}
