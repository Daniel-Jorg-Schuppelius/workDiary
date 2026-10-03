<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeOverviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Project;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Classification\Tag;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Zeitenübersicht über alle Projekte (MVP-1073): Header-Zeitraum, Sicht auf
 * fremde Zeiten, Filter, Gruppierung und Rücksprung aus dem Bearbeiten.
 *
 * Der Zeitraum wird immer per WithGlobalDateRange gepinnt und jedes Datum
 * ausdrücklich gesetzt.
 */
class ProjectTimeOverviewTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private User $user;

    private Project $alpha;

    private Project $beta;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->user = $this->orgUser();
        $this->alpha = $this->createProject('Alpha-Projekt');
        $this->beta = $this->createProject('Beta-Projekt');
    }

    public function test_lists_entries_of_all_projects_in_the_global_range(): void {
        $this->createEntry($this->alpha, '2026-06-10', 'Alpha-im-Juni');
        $this->createEntry($this->beta, '2026-06-30', 'Beta-am-Monatsletzten');
        $this->createEntry($this->alpha, '2026-05-10', 'Alpha-im-Mai');

        $response = $this->overview($this->admin);

        $response->assertOk();
        $response->assertSee('Alpha-im-Juni');
        $response->assertSee('Beta-am-Monatsletzten');
        $response->assertDontSee('Alpha-im-Mai');
        $response->assertViewHas('totals', ['count' => 2, 'minutes' => 240, 'billable' => 240, 'projects' => 2]);
        $response->assertViewHas('groups', fn(Collection $groups): bool => $groups->count() === 2);
    }

    public function test_entries_without_project_are_left_out(): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'date' => '2026-06-10',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'description' => 'Verwaltungszeit-ohne-Projekt',
        ]);

        $response = $this->overview($this->admin);

        $response->assertOk();
        $response->assertDontSee('Verwaltungszeit-ohne-Projekt');
        $response->assertViewHas('totals', fn(array $totals): bool => $totals['count'] === 0);
    }

    public function test_plain_user_sees_only_own_entries(): void {
        $this->createEntry($this->alpha, '2026-06-10', 'Eigene-Zeit', $this->user);
        $this->createEntry($this->alpha, '2026-06-11', 'Fremde-Zeit', $this->admin);

        $response = $this->overview($this->user, ['group' => 'user', 'user' => $this->admin->sqid]);

        $response->assertOk();
        $response->assertSee('Eigene-Zeit');
        $response->assertDontSee('Fremde-Zeit');
        $response->assertViewHas('seesAll', false);
        // Gruppierung und Filter nach Person gibt es nur mit Sicht auf fremde Zeiten.
        $response->assertViewHas('group', 'project');
        $response->assertViewHas('filters', fn(array $filters): bool => $filters['user'] === '');
    }

    public function test_accounting_sees_all_and_filters_by_user(): void {
        $accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->createEntry($this->alpha, '2026-06-10', 'Zeit-von-User', $this->user);
        $this->createEntry($this->alpha, '2026-06-11', 'Zeit-von-Admin', $this->admin);

        $this->overview($accounting)
            ->assertOk()
            ->assertSee('Zeit-von-User')
            ->assertSee('Zeit-von-Admin');

        $this->overview($accounting, ['user' => $this->user->sqid])
            ->assertOk()
            ->assertSee('Zeit-von-User')
            ->assertDontSee('Zeit-von-Admin');
    }

    public function test_filters_by_project_customer_tag_billable_and_search(): void {
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Filter-Kunde']);
        $this->beta->update(['customer_id' => $customer->id]);
        $tag = Tag::create(['organization_id' => $this->organization->id, 'name' => 'Wartung', 'slug' => 'wartung']);

        $alphaEntry = $this->createEntry($this->alpha, '2026-06-10', 'Alpha-Eintrag');
        $alphaEntry->tags()->attach($tag->id);
        $this->createEntry($this->beta, '2026-06-11', 'Beta-Eintrag', billable: false);

        $expectations = [
            [['project' => $this->alpha->sqid], 'Alpha-Eintrag', 'Beta-Eintrag'],
            [['customer' => $customer->sqid], 'Beta-Eintrag', 'Alpha-Eintrag'],
            [['tag' => $tag->sqid], 'Alpha-Eintrag', 'Beta-Eintrag'],
            [['billable' => 'no'], 'Beta-Eintrag', 'Alpha-Eintrag'],
            [['billable' => 'yes'], 'Alpha-Eintrag', 'Beta-Eintrag'],
            [['q' => 'Beta-Proj'], 'Beta-Eintrag', 'Alpha-Eintrag'],
            [['q' => 'Alpha-Ein'], 'Alpha-Eintrag', 'Beta-Eintrag'],
        ];
        foreach ($expectations as [$query, $visible, $hidden]) {
            $this->overview($this->admin, $query)
                ->assertOk()
                ->assertSee($visible)
                ->assertDontSee($hidden);
        }
    }

    public function test_array_input_in_filters_does_not_break_the_page(): void {
        $this->createEntry($this->alpha, '2026-06-10', 'Robuster-Eintrag');

        $this->overview($this->admin, ['q' => ['x'], 'project' => ['y'], 'group' => ['z']])
            ->assertOk()
            ->assertSee('Robuster-Eintrag');
    }

    public function test_group_header_counts_the_whole_range_not_only_the_page(): void {
        foreach (range(1, 52) as $i) {
            $this->createEntry($this->alpha, '2026-06-' . str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT), 'Serie-' . $i);
        }

        $response = $this->overview($this->admin);

        $response->assertOk();
        $response->assertViewHas('entries', fn($entries): bool => $entries->count() === 50 && $entries->total() === 52);
        $response->assertViewHas('groups', function (Collection $groups): bool {
            $group = $groups->first();

            return $groups->count() === 1 && $group['count'] === 52 && $group['minutes'] === 52 * 120;
        });
    }

    public function test_groups_by_day_and_by_user(): void {
        $this->createEntry($this->alpha, '2026-06-10', 'Tag-eins-Alpha', $this->user);
        $this->createEntry($this->beta, '2026-06-10', 'Tag-eins-Beta');
        $this->createEntry($this->beta, '2026-06-12', 'Tag-zwei-Beta');

        $byDay = $this->overview($this->admin, ['group' => 'day']);
        $byDay->assertOk();
        $byDay->assertViewHas('group', 'day');
        $byDay->assertViewHas('groups', function (Collection $groups): bool {
            return $groups->keys()->all() === ['2026-06-12', '2026-06-10']
                && $groups->get('2026-06-10')['count'] === 2
                && $groups->get('2026-06-10')['minutes'] === 240;
        });

        $byUser = $this->overview($this->admin, ['group' => 'user']);
        $byUser->assertOk();
        $byUser->assertViewHas('group', 'user');
        $byUser->assertViewHas('groups', fn(Collection $groups): bool => $groups->count() === 2
            && $groups->get($this->user->id)['count'] === 1
            && $groups->get($this->admin->id)['count'] === 2);
    }

    public function test_invalid_sort_falls_back_to_date(): void {
        $this->createEntry($this->alpha, '2026-06-10', 'Sortier-Eintrag');

        $this->overview($this->admin, ['sort' => 'organization_id', 'dir' => 'asc'])
            ->assertOk()
            ->assertViewHas('sort', 'date')
            ->assertViewHas('dir', 'desc');

        foreach (['project', 'user', 'minutes', 'task', 'description'] as $sort) {
            $this->overview($this->admin, ['sort' => $sort, 'dir' => 'asc'])
                ->assertOk()
                ->assertViewHas('sort', $sort)
                ->assertSee('Sortier-Eintrag');
        }
    }

    public function test_edit_dialog_opened_from_the_overview_carries_the_return_target(): void {
        $entry = $this->createEntry($this->alpha, '2026-06-10', 'Dialog-Eintrag');

        $this->actingAs($this->admin)
            ->get(route('projects.time-entries.edit', [$this->alpha, $entry, 'return_to' => 'times']))
            ->assertOk()
            ->assertSee('name="return_to" value="times"', false);

        $this->actingAs($this->admin)
            ->get(route('projects.time-entries.edit', [$this->alpha, $entry]))
            ->assertOk()
            ->assertDontSee('name="return_to"', false);
    }

    public function test_update_and_delete_return_to_the_overview_when_asked(): void {
        $entry = $this->createEntry($this->alpha, '2026-06-10', 'Rücksprung-Eintrag');

        $this->actingAs($this->admin)
            ->put(route('projects.time-entries.update', [$this->alpha, $entry]), [
                'date' => '2026-06-10',
                'minutes' => 120,
                'description' => 'Rücksprung-geändert',
                'return_to' => 'times',
            ])
            ->assertRedirect(route('projects.times'));
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'description' => 'Rücksprung-geändert']);

        $this->actingAs($this->admin)
            ->delete(route('projects.time-entries.destroy', [$this->alpha, $entry]), ['return_to' => 'times'])
            ->assertRedirect(route('projects.times'));
        $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
    }

    public function test_update_without_return_target_goes_back_to_the_project(): void {
        $entry = $this->createEntry($this->alpha, '2026-06-10', 'Projekt-Rücksprung');

        $this->actingAs($this->admin)
            ->put(route('projects.time-entries.update', [$this->alpha, $entry]), [
                'date' => '2026-06-10',
                'minutes' => 90,
                'return_to' => 'https://example.org/',
            ])
            ->assertRedirect(route('projects.show', ['project' => $this->alpha, '#' => 'time']));
    }

    public function test_project_list_links_to_the_overview(): void {
        $this->actingAs($this->admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(route('projects.times'), false);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function overview(User $viewer, array $query = []): TestResponse {
        return $this->actingAs($viewer)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('projects.times', $query));
    }

    private function createProject(string $name): Project {
        return Project::create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
            'billable' => true,
        ]);
    }

    private function createEntry(Project $project, string $date, string $description, ?User $user = null, bool $billable = true): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => ($user ?? $this->admin)->id,
            'date' => $date,
            'started_at' => $date . ' 09:00:00',
            'ended_at' => $date . ' 11:00:00',
            'kind' => TimeEntryKind::Work->value,
            'billable' => $billable,
            'description' => $description,
        ]);
    }
}
