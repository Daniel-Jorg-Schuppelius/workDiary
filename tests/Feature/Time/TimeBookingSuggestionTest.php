<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeBookingSuggestionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Enums\Project\ProjectStatus;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{Attendance, TimeEntry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-923: Projektvorschläge für offene Zeitblöcke und Sammelübernahme. */
final class TimeBookingSuggestionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $orderProject;

    private Project $recentProject;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-09-28 14:00:00');
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->orderProject = Project::create(['organization_id' => $this->organization->id, 'name' => 'Wartung Halle', 'status' => ProjectStatus::Active->value, 'created_by' => $this->user->id]);
        $this->recentProject = Project::create(['organization_id' => $this->organization->id, 'name' => 'Verwaltung', 'status' => ProjectStatus::Active->value, 'created_by' => $this->user->id]);
        Attendance::create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'started_at' => Carbon::parse('2026-09-28 08:00:00'), 'ended_at' => Carbon::parse('2026-09-28 12:00:00'), 'date' => Carbon::parse('2026-09-28')]);
        TimeEntry::create(['organization_id' => $this->organization->id, 'project_id' => $this->recentProject->id, 'user_id' => $this->user->id, 'date' => '2026-09-27', 'minutes' => 30]);
    }

    public function test_block_overlapping_an_own_order_suggests_its_project(): void {
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'assigned_user_id' => $this->user->id, 'project_id' => $this->orderProject->id, 'title' => 'Pumpe prüfen', 'start_at' => Carbon::parse('2026-09-28 09:00:00'), 'end_at' => Carbon::parse('2026-09-28 10:00:00')]);

        $html = $this->actingAs($this->user)->get(route('today.show'))->assertOk()->getContent();

        $this->assertStringContainsString(__('time_entry.suggestion.source.order', ['title' => 'Pumpe prüfen']), (string) $html);
        $this->assertMatchesRegularExpression('/<option value="' . $this->orderProject->sqid . '"[^>]*selected/', (string) $html);
    }

    public function test_without_orders_the_last_booked_project_is_suggested(): void {
        $html = $this->actingAs($this->user)->get(route('today.show'))->assertOk()->getContent();

        $this->assertStringContainsString(__('time_entry.suggestion.source.recent'), (string) $html);
        $this->assertMatchesRegularExpression('/<option value="' . $this->recentProject->sqid . '"[^>]*selected/', (string) $html);
    }

    public function test_all_suggestions_are_booked_in_one_step(): void {
        $this->actingAs($this->user)->post(route('today.quick-book-all'), [
            'date' => '2026-09-28',
            'blocks' => [
                ['started_at' => '2026-09-28T08:00:00Z', 'ended_at' => '2026-09-28T10:00:00Z', 'project' => $this->orderProject->sqid],
                ['started_at' => '2026-09-28T10:00:00Z', 'ended_at' => '2026-09-28T12:00:00Z', 'project' => $this->recentProject->sqid],
                ['started_at' => '2026-09-28T12:00:00Z', 'ended_at' => '2026-09-28T12:30:00Z', 'project' => ''],
            ],
        ])->assertRedirect(route('today.show', ['date' => '2026-09-28']))->assertSessionHas('status');

        $this->assertSame([120, 120], TimeEntry::query()->whereNotNull('started_at')->orderBy('started_at')->pluck('minutes')->map(fn ($m): int => (int) $m)->all());
    }

    public function test_foreign_projects_cannot_be_booked(): void {
        $other = \App\Models\Platform\Organization::factory()->create();
        $foreign = Project::create(['organization_id' => $other->id, 'name' => 'Fremd', 'status' => ProjectStatus::Active->value, 'created_by' => $this->user->id]);

        $this->actingAs($this->user)->post(route('today.quick-book-all'), ['blocks' => [['started_at' => '2026-09-28T08:00:00Z', 'ended_at' => '2026-09-28T09:00:00Z', 'project' => $foreign->sqid]]])->assertNotFound();
        $this->assertSame(0, TimeEntry::query()->whereNotNull('started_at')->count());
    }
}
