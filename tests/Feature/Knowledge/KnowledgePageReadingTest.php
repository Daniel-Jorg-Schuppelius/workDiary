<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageReadingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\Knowledge\{KnowledgeAudienceKind, KnowledgePageStatus};
use App\Enums\Notification\NotificationEvent;
use App\Enums\User\Permission;
use App\Models\Knowledge\{KnowledgePage, KnowledgePageRead, KnowledgeSpace};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\{Team, User};
use App\Services\Knowledge\Pages\DeadlineScans\KnowledgePageReviewScan;
use App\Services\Knowledge\Pages\{KnowledgePageService, KnowledgeReadingService};
use App\Services\Knowledge\Pages\WorkQueue\{KnowledgeReadingWorkItemSource, KnowledgeReviewWorkItemSource};
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Pflichtlektüre mit Lesebestätigung je Fassung (MVP-1666) sowie Prüfintervall,
 * Gültigkeit und Arbeitsvorrat (MVP-1665).
 */
final class KnowledgePageReadingTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private KnowledgeSpace $space;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_new_approved_version_requires_a_new_confirmation(): void {
        $page = $this->approvedPage('Datenschutz im Alltag', 'Fassung eins');
        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);

        $this->actingAs($this->admin)->post(route('knowledge.pages.assignments.store', $page), ['kind' => 'all', 'is_onboarding' => '1'])
            ->assertSessionHasNoErrors();

        $readings = app(KnowledgeReadingService::class);
        $this->assertCount(1, $readings->openFor($reader));
        $this->actingAs($reader)->get(route('knowledge.readings.index'))->assertOk()->assertSee('Datenschutz im Alltag');
        $items = iterator_to_array(app(KnowledgeReadingWorkItemSource::class)->items($reader, $this->organization), false);
        $this->assertCount(1, $items);

        $this->actingAs($reader)->post(route('knowledge.pages.confirm-read', $page))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('knowledge_page_reads', ['knowledge_page_id' => $page->id, 'user_id' => $reader->id, 'version' => 1]);
        $this->assertSame([], $readings->openFor($reader));
        $this->assertTrue($readings->onboardingFor($reader)[0]['confirmed']);

        // Neue Fassung freigegeben: Bestätigung erneut fällig, Übersicht zeigt die ältere.
        $service = app(KnowledgePageService::class);
        $service->update($page, $this->admin, ['title' => 'Datenschutz im Alltag', 'body' => 'Fassung zwei'], 1);
        $service->approve($page->refresh(), $this->admin);
        $open = $readings->openFor($reader);
        $this->assertCount(1, $open);
        $this->assertSame(1, $open[0]['confirmed_version']);

        $row = collect($readings->overview($page->refresh()))->firstWhere('user.id', $reader->id);
        $this->assertNotNull($row);
        $this->assertFalse($row['current']);
        $this->assertSame(1, $row['version']);
        $this->actingAs($this->admin)->get(route('knowledge.pages.readings', $page))->assertOk()->assertSee($reader->name);
    }

    public function test_assignments_by_team_and_person_reach_only_their_members(): void {
        $page = $this->approvedPage('Lagerregeln', 'Text');
        $member = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $other = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $person = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $member->teams()->attach($team->id);

        $readings = app(KnowledgeReadingService::class);
        $readings->assign($page, $this->admin, KnowledgeAudienceKind::Team, null, (int) $team->id, null, false);
        $readings->assign($page, $this->admin, KnowledgeAudienceKind::User, null, null, (int) $person->id, false);

        $required = $readings->requiredReaders($page)->pluck('id')->all();
        $this->assertContains($member->id, $required);
        $this->assertContains($person->id, $required);
        $this->assertNotContains($other->id, $required);
        $this->assertSame([], $readings->openFor($other));
    }

    public function test_only_a_valid_page_can_be_confirmed(): void {
        $page = $this->approvedPage('Veraltete Regel', 'Text');
        app(KnowledgePageService::class)->markOutdated($page);
        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);

        $this->actingAs($reader)->post(route('knowledge.pages.confirm-read', $page->refresh()))->assertSessionHasErrors('page');
        $this->assertSame(0, KnowledgePageRead::query()->count());
    }

    public function test_review_scan_expires_pages_and_notifies_the_owner_once(): void {
        $expired = $this->approvedPage('Abgelaufen', 'x');
        $expired->forceFill(['valid_until' => CarbonImmutable::today()->subDay()->toDateString()])->save();
        $due = $this->approvedPage('Prüfen bitte', 'y');
        $due->forceFill(['next_review_on' => CarbonImmutable::today()->addDays(3)->toDateString(), 'review_warned_at' => null])->save();

        $scan = app(KnowledgePageReviewScan::class);
        $scan->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30));

        $this->assertSame(KnowledgePageStatus::Outdated, $expired->refresh()->status);
        $this->assertNotNull($due->refresh()->review_warned_at);
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::KnowledgePageReviewDue->value)->count());

        // Ein zweiter Lauf meldet dieselbe Frist nicht noch einmal.
        $scan->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30));
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::KnowledgePageReviewDue->value)->count());

        $titles = collect(iterator_to_array(app(KnowledgeReviewWorkItemSource::class)->items($this->admin, $this->organization), false))->pluck('title')->implode(' | ');
        $this->assertStringContainsString('Prüfen bitte', $titles);
        $this->assertStringContainsString('Abgelaufen', $titles);
    }

    private function approvedPage(string $title, string $body): KnowledgePage {
        $service = app(KnowledgePageService::class);
        $page = $service->create($this->space, null, null, $this->admin, ['title' => $title, 'body' => $body]);

        return $service->approve($page, $this->admin);
    }
}
