<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallDetectorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\Project\ShortfallStatus;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Services\Project\TimeTargets\ProjectTimeShortfallDetector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abgleich der Projekt-Sollzeiten (Feature 194, MVP-1468): Befund über der
 * Toleranz, Erledigen durch Nachbuchung, Wiedereröffnen, bestätigt bleibt
 * bestätigt, Gerätelaufzeit zählt nicht; der Befehl läuft je Organisation.
 */
class ProjectTimeShortfallDetectorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $anna;

    private User $ben;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->ben = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Ben Muster']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schule Nord']);
        $this->project->members()->attach([$this->anna->id, $this->ben->id]);
        // Projektvorgabe 8 h Mo–Fr; der 11.03.2030 ist ein Montag.
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => $weekday, 'target_minutes' => 480]);
        }
    }

    public function test_shortfall_above_tolerance_is_recorded_and_resolved_by_a_late_booking(): void {
        $this->book($this->anna, '2030-03-11', 390);
        $this->book($this->ben, '2030-03-11', 470);
        // Gerätelaufzeit ist keine Personenzeit und füllt das Soll nicht.
        TimeEntry::factory()->machine()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11']);

        $detector = app(ProjectTimeShortfallDetector::class);
        $day = CarbonImmutable::parse('2030-03-11');
        $result = $detector->checkRange($this->project, $day, $day, 15);

        $this->assertSame(1, $result['created']);
        $shortfall = ProjectTimeShortfall::query()->where('user_id', $this->anna->id)->firstOrFail();
        $this->assertSame([480, 390, 90, ShortfallStatus::Open], [$shortfall->target_minutes, $shortfall->booked_minutes, $shortfall->shortfall_minutes, $shortfall->status]);
        $this->assertSame(0, ProjectTimeShortfall::query()->where('user_id', $this->ben->id)->count(), 'Zehn Minuten liegen in der Toleranz');

        $this->book($this->anna, '2030-03-11', 120);
        $again = $detector->checkRange($this->project, $day, $day, 15);
        $this->assertSame(1, $again['resolved']);
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->fresh()->status);
        $this->assertNotNull($shortfall->fresh()->resolved_at);

        // Buchung weg → der Befund öffnet sich wieder und gilt erneut als neu entdeckt.
        TimeEntry::query()->where('user_id', $this->anna->id)->where('minutes', 120)->delete();
        $third = $detector->checkRange($this->project, $day, $day, 15);
        $this->assertSame(1, $third['reopened']);
        $this->assertSame(ShortfallStatus::Open, $shortfall->fresh()->status);
        $this->assertCount(1, $third['detected']);
    }

    public function test_acknowledged_shortfall_stays_acknowledged_while_the_gap_remains(): void {
        $this->book($this->anna, '2030-03-12', 300);
        $detector = app(ProjectTimeShortfallDetector::class);
        $day = CarbonImmutable::parse('2030-03-12');
        $detector->checkRange($this->project, $day, $day, 15);
        $shortfall = ProjectTimeShortfall::query()->where('user_id', $this->anna->id)->firstOrFail();
        $shortfall->update(['status' => ShortfallStatus::Acknowledged, 'acknowledged_at' => now()]);

        $this->book($this->anna, '2030-03-12', 30);
        $result = $detector->checkRange($this->project, $day, $day, 15);

        $this->assertSame(2, $result['unchanged'], 'Anna bestätigt, Ben ohne Buchung weiter offen');
        $fresh = $shortfall->fresh();
        $this->assertSame(ShortfallStatus::Acknowledged, $fresh->status);
        $this->assertSame(150, $fresh->shortfall_minutes, 'Die Zahlen folgen der Buchung, der Status bleibt');
    }

    public function test_command_checks_the_range_per_organization(): void {
        $this->book($this->anna, '2030-03-13', 200);
        $this->book($this->anna, '2030-03-14', 480);

        $this->artisan('projects:check-time-targets', ['--date' => '2030-03-14', '--days' => 2, '--organization' => $this->organization->id])
            ->expectsOutputToContain('Schule Nord: 3 neu unter Soll')
            ->assertSuccessful();

        $this->assertSame(['2030-03-13'], ProjectTimeShortfall::query()->where('user_id', $this->anna->id)->pluck('date')->map(static fn ($d): string => $d->toDateString())->all());
        $this->assertSame(2, ProjectTimeShortfall::query()->where('user_id', $this->ben->id)->count(), 'Ben hat an beiden Tagen nichts gebucht');
    }

    private function book(User $user, string $date, int $minutes): void {
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'date' => $date,
            'minutes' => $minutes,
        ]);
    }
}
