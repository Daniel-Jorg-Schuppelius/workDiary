<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderVisibilityInListsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Diary;

use App\Enums\User\Permission;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Timeline\DiaryEntryTimelineService;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Entscheidung nach dem UI-Vollcrawl 2026-10-03: Kundenseite und
 * Kommunikationsnotizen listen Aufträge anderer nur noch, wenn der
 * Betrachter sie öffnen darf (eigener Auftrag oder `diary.viewAny`) —
 * vorher zeigten sie Links, die im 403 endeten.
 */
class OrderVisibilityInListsTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $worker;

    private User $viewAll;

    private Customer $customer;

    private Project $project;

    private DiaryEntry $own;

    private DiaryEntry $foreign;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->worker = $this->orgUser();
        $colleague = $this->orgUser();
        $this->viewAll = $this->orgUser();
        $this->grantPermissions($this->viewAll, [Permission::DiaryViewAny]);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $this->own = $this->order($this->worker, 'Eigener-Auftrag');
        $this->foreign = $this->order($colleague, 'Fremder-Auftrag');

        $this->note($this->own, 'Notiz-am-eigenen-Auftrag', $this->worker);
        $this->note($this->foreign, 'Notiz-am-fremden-Auftrag', $colleague);
        CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => MorphMap::alias(Customer::class),
            'notable_id' => $this->customer->id,
            'created_by_user_id' => $colleague->id,
            'subject' => 'Notiz-am-Kunden',
        ]);
    }

    public function test_customer_timeline_lists_only_orders_the_viewer_may_open(): void {
        $timeline = app(DiaryEntryTimelineService::class);
        $this->actAsTeam($this->organization);

        $this->actingAs($this->worker);
        $own = collect($timeline->forCustomer($this->customer, $this->worker, ['order'])['items']);
        $this->assertSame(['Eigener-Auftrag'], $own->pluck('summary')->unique()->values()->all());

        $this->actingAs($this->viewAll);
        $all = collect($timeline->forCustomer($this->customer, $this->viewAll, ['order'])['items']);
        $this->assertEqualsCanonicalizing(['Eigener-Auftrag', 'Fremder-Auftrag'], $all->pluck('summary')->unique()->values()->all());
    }

    public function test_customer_page_has_no_link_to_foreign_orders(): void {
        $this->actingAs($this->worker)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('diary.show', $this->own), false)
            ->assertDontSee(route('diary.show', $this->foreign), false)
            ->assertDontSee('Notiz-am-fremden-Auftrag');

        $this->actingAs($this->viewAll)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('diary.show', $this->foreign), false);
    }

    public function test_project_page_has_no_link_to_foreign_orders(): void {
        // Reiter und Timeline folgen dem Header-Zeitraum — weit genug für die Factory-Daten.
        $range = $this->dateRangeSession(now()->subYear()->toDateString(), now()->addYear()->toDateString());

        $this->actingAs($this->worker)->withSession($range)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee(route('diary.show', $this->own), false)
            ->assertDontSee(route('diary.show', $this->foreign), false);

        $this->actingAs($this->viewAll)->withSession($range)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee(route('diary.show', $this->foreign), false);
    }

    public function test_notes_list_hides_notes_on_orders_the_viewer_may_not_open(): void {
        $this->actingAs($this->worker)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Notiz-am-eigenen-Auftrag')
            ->assertSee('Notiz-am-Kunden')
            ->assertDontSee('Notiz-am-fremden-Auftrag')
            ->assertDontSee(route('diary.show', $this->foreign), false);

        $this->actingAs($this->viewAll)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Notiz-am-fremden-Auftrag')
            ->assertSee(route('diary.show', $this->foreign), false);
    }

    public function test_note_dialog_links_the_order_only_when_it_can_be_opened(): void {
        $note = CommunicationNote::query()->where('subject', 'Notiz-am-fremden-Auftrag')->firstOrFail();

        // Über Suche oder Direktlink bleibt die Notiz nach ihrer eigenen Regel erreichbar.
        $this->actingAs($this->worker)->get(route('communication-notes.show', $note))
            ->assertOk()
            ->assertDontSee(route('diary.show', $this->foreign), false);

        $this->actingAs($this->viewAll)->get(route('communication-notes.show', $note))
            ->assertOk()
            ->assertSee(route('diary.show', $this->foreign), false);
    }

    /**
     * Team-Sicht (Auftragsliste, Kanban, Wochenansicht) bleibt offen: fremde
     * Aufträge stehen da, aber ohne Link — der endete im 403.
     */
    public function test_team_views_show_foreign_orders_without_a_link(): void {
        $today = ['start_at' => now()->startOfDay()->addHours(9), 'end_at' => now()->startOfDay()->addHours(10), 'is_archived' => false];
        $mine = DiaryEntry::factory()->for($this->worker)->create(['organization_id' => $this->organization->id, 'content' => 'Team-eigener-Inhalt'] + $today);
        $theirs = DiaryEntry::factory()->for($this->viewAll)->create(['organization_id' => $this->organization->id, 'content' => 'Team-fremder-Inhalt'] + $today);
        $this->actingAs($this->worker)->get(route('diary.show', $theirs))->assertForbidden();

        $pages = [
            'Auftragsliste' => route('diary.index'),
            'Kanban' => route('kanban.index', ['scope' => 'team']),
            'Wochenansicht' => route('week.index', ['scope' => 'team']),
        ];
        foreach ($pages as $name => $url) {
            $this->actingAs($this->worker)->get($url)
                ->assertOk()
                ->assertSee('Team-fremder-Inhalt')
                ->assertSee(route('diary.show', $mine), false)
                ->assertDontSee(route('diary.show', $theirs), false);

            $this->actingAs($this->viewAll)->get($url)
                ->assertOk()
                ->assertSee(route('diary.show', $mine), false);
        }
    }

    /** Zugewiesene dürfen lesen, was sie bearbeiten (Entscheidung 2026-10-04). */
    public function test_assignee_may_open_the_order_and_sees_it_in_lists(): void {
        $assignee = $this->orgUser();
        $this->foreign->update(['assigned_user_id' => $assignee->id]);

        $this->actingAs($assignee)->get(route('diary.show', $this->foreign))->assertOk();
        $this->actingAs($assignee)->get(route('diary.case-file', $this->foreign))->assertOk();
        // Ändern bleibt dem Eigentümer oder diary.viewAny + diary.update.
        $this->actingAs($assignee)->get(route('diary.edit', $this->foreign))->assertForbidden();

        $this->actingAs($assignee)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('diary.show', $this->foreign), false)
            ->assertDontSee(route('diary.show', $this->own), false);
        $this->actingAs($assignee)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Notiz-am-fremden-Auftrag')
            ->assertDontSee('Notiz-am-eigenen-Auftrag');
    }

    /**
     * Die Fallakte (Seite und PDF) trägt dasselbe Leserecht wie die
     * Auftragsdetailseite — die Prüfung fehlte bis 2026-10-04.
     */
    public function test_case_file_requires_the_right_to_view_the_order(): void {
        $this->actingAs($this->worker)->get(route('diary.case-file', $this->foreign))->assertForbidden();
        $this->actingAs($this->worker)->get(route('diary.case-file.pdf', $this->foreign))->assertForbidden();

        $this->actingAs($this->worker)->get(route('diary.case-file', $this->own))->assertOk();
        $this->actingAs($this->viewAll)->get(route('diary.case-file', $this->foreign))->assertOk();
    }

    private function order(User $owner, string $title): DiaryEntry {
        return DiaryEntry::factory()->for($owner)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'title' => $title,
        ]);
    }

    private function note(DiaryEntry $order, string $subject, User $creator): CommunicationNote {
        return CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => MorphMap::alias(DiaryEntry::class),
            'notable_id' => $order->id,
            'created_by_user_id' => $creator->id,
            'subject' => $subject,
        ]);
    }
}
