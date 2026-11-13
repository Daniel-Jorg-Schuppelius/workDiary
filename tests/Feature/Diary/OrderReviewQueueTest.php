<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderReviewQueueTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Dashboard\Widgets\OrderReviewWidget;
use App\Enums\Diary\Status;
use App\Models\Approval\Approval;
use App\Models\CarryForward\CarryForwardNote;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\Approval\ApprovalInbox;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Diary\Demo\PeriodOrderDemoBlock;
use App\Services\Diary\OrderService;
use App\Services\Diary\Review\ReviewQueue;
use App\Services\Diary\WorkQueue\AssignedEntryWorkItemSource;
use App\Services\WorkQueue\WorkQueue;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prüf-Warteschlange und Arbeitsreihenfolge (Feature 198, MVP-1636):
 * Genehmigungs-Eingang ohne eigene Vorlagen, Reihenfolge Fälligkeit dann
 * Vorlagedatum, „Meine Aufträge“ in Abarbeitungsreihenfolge, Kachel und
 * Demo-Block.
 */
final class OrderReviewQueueTest extends TestCase {
    use RefreshDatabase;

    private User $worker;

    private User $reviewer;

    private OrderService $orders;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->worker = User::factory()->user()->create();
        $this->reviewer = User::factory()->teamleitung()->create(['organization_id' => $this->worker->organization_id]);
        $this->organization = Organization::query()->findOrFail($this->worker->organization_id);
        app()->instance('currentOrganization', $this->organization);
        $this->orders = app(OrderService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function submitted(?string $due = null, ?User $submitter = null, array $attributes = []): DiaryEntry {
        $entry = DiaryEntry::factory()->for($this->worker)->create(array_merge([
            'organization_id' => $this->worker->organization_id,
            'assigned_user_id' => $this->worker->id,
            'status' => Status::InProgress,
            'requires_review' => true,
            'due_date' => $due,
        ], $attributes));

        return $this->orders->submitForReview($entry, $submitter ?? $this->worker);
    }

    /** Akzeptanzkriterium 2: wer vorgelegt hat, sieht den Auftrag nicht in der eigenen Prüf-Warteschlange. */
    public function test_inbox_shows_submissions_to_reviewers_but_never_the_own(): void {
        $foreign = $this->submitted();
        $own = $this->submitted(submitter: $this->reviewer);

        $steps = app(ApprovalInbox::class)->openStepsFor($this->reviewer);
        $this->assertSame([$foreign->id], $steps->map(static fn (Approval $a): int => (int) $a->approvable_id)->all());

        $queue = app(ReviewQueue::class)->for($this->reviewer, (int) $this->worker->organization_id)->pluck('id')->all();
        $this->assertSame([$foreign->id], $queue);
        $this->assertNotContains($own->id, $queue);

        $this->actingAs($this->reviewer)->get(route('diary.review-queue'))
            ->assertOk()
            ->assertSee(route('diary.show', $foreign), false)
            ->assertDontSee(route('diary.show', $own), false);
    }

    public function test_queue_is_sorted_by_due_date_then_submission_date(): void {
        CarbonImmutable::setTestNow('2030-03-01 08:00:00');
        $late = $this->submitted('2030-03-20');
        CarbonImmutable::setTestNow('2030-03-01 09:00:00');
        $noDue = $this->submitted();
        CarbonImmutable::setTestNow('2030-03-01 10:00:00');
        $soonSecond = $this->submitted('2030-03-05');
        CarbonImmutable::setTestNow('2030-03-01 07:00:00');
        $soonFirst = $this->submitted('2030-03-05');
        CarbonImmutable::setTestNow();

        $this->assertSame(
            [$soonFirst->id, $soonSecond->id, $late->id, $noDue->id],
            app(ReviewQueue::class)->for($this->reviewer, (int) $this->worker->organization_id)->pluck('id')->all(),
        );
    }

    public function test_named_reviewer_gets_the_submission_and_others_with_the_right_do_not(): void {
        $named = User::factory()->user()->create(['organization_id' => $this->worker->organization_id]);
        $entry = $this->submitted(attributes: ['reviewer_user_id' => $named->id]);

        $this->assertSame([$entry->id], app(ReviewQueue::class)->for($named, (int) $this->worker->organization_id)->pluck('id')->all());
        $this->assertSame([], app(ReviewQueue::class)->for($this->reviewer, (int) $this->worker->organization_id)->pluck('id')->all());
        $this->assertTrue((new OrderReviewWidget(app(ReviewQueue::class)))->availableFor($named));
        $this->assertFalse((new OrderReviewWidget(app(ReviewQueue::class)))->availableFor($this->worker));
    }

    /**
     * Benannte prüfende Person ohne Genehmigungsrecht (Feature 198): Der
     * Genehmigungs-Eingang bleibt ihr verschlossen; Kachel und
     * Prüf-Warteschlange genügen, dort gibt sie den Auftrag frei.
     */
    public function test_named_reviewer_without_approval_right_approves_from_the_queue(): void {
        $named = User::factory()->user()->create(['organization_id' => $this->worker->organization_id]);
        $this->assertFalse($named->can(\App\Enums\User\Permission::ServiceRequestApprove->value));
        $this->assertFalse($named->can(\App\Enums\User\Permission::OrderReview->value));
        $entry = $this->submitted(attributes: ['reviewer_user_id' => $named->id]);

        $this->actingAs($named)->get(route('approvals.index'))->assertForbidden();
        $this->assertTrue((new OrderReviewWidget(app(ReviewQueue::class)))->availableFor($named));
        $this->actingAs($named)->get(route('diary.review-queue'))
            ->assertOk()
            ->assertSee(route('diary.lifecycle', [$entry, 'action' => 'approveReview']), false);

        $this->actingAs($named)
            ->from(route('diary.review-queue'))
            ->post(route('diary.lifecycle', [$entry, 'action' => 'approveReview']))
            ->assertRedirect(route('diary.review-queue'))
            ->assertSessionHasNoErrors();
        $this->assertSame(Status::ReviewApproved, $entry->refresh()->status);
        $this->assertTrue(Approval::query()
            ->where('approvable_type', $entry->getMorphClass())
            ->where('approvable_id', $entry->id)
            ->where('decision', 'approved')
            ->where('decided_by', $named->id)
            ->exists());
    }

    public function test_inbox_decisions_run_on_the_order(): void {
        $approve = $this->submitted();
        $query = $this->submitted();
        $inbox = app(ApprovalInbox::class);
        $steps = $inbox->openStepsFor($this->reviewer)->keyBy('approvable_id');

        $this->actingAs($this->reviewer)->post(route('approvals.decide', $steps[$approve->id]), ['decision' => 'approved'])->assertRedirect(route('approvals.index'));
        $this->assertSame(Status::ReviewApproved, $approve->refresh()->status);

        $this->actingAs($this->reviewer)->post(route('approvals.decide', $steps[$query->id]), ['decision' => 'question', 'reason' => 'Welche Belege fehlen?'])->assertRedirect(route('approvals.index'));
        $this->assertSame(Status::ReviewQueried, $query->refresh()->status);
        $this->assertSame([], $inbox->openStepsFor($this->reviewer)->all());

        // Erneut vorgelegt erscheint die offene Stufe wieder.
        $this->orders->resubmitForReview($query, $this->worker);
        $this->assertSame([$query->id], $inbox->openStepsFor($this->reviewer)->map(static fn (Approval $a): int => (int) $a->approvable_id)->all());
    }

    public function test_my_orders_follow_the_working_order(): void {
        $make = fn (Status $status, string $due): DiaryEntry => DiaryEntry::factory()->for($this->worker)->create([
            'organization_id' => $this->worker->organization_id,
            'assigned_user_id' => $this->worker->id,
            'status' => $status,
            'requires_review' => true,
            'due_date' => $due,
            'title' => $status->name,
        ]);
        $far = '2099-12-31';
        $accepted = $make(Status::Accepted, '2099-01-01');
        $inProgress = $make(Status::InProgress, $far);
        $approved = $make(Status::ReviewApproved, $far);
        $queried = $make(Status::ReviewQueried, $far);

        $items = iterator_to_array(app(AssignedEntryWorkItemSource::class)->items($this->worker, $this->organization), false);
        $this->assertSame([$queried->id, $approved->id, $inProgress->id, $accepted->id], array_map(static fn ($item): int => (int) substr($item->key, strrpos($item->key, ':') + 1), $items));

        $queue = array_values(array_filter(
            app(WorkQueue::class)->itemsFor($this->worker, $this->organization),
            static fn ($item): bool => $item->source === 'assigned_entry',
        ));
        $this->assertSame(['ReviewQueried', 'ReviewApproved', 'InProgress', 'Accepted'], array_map(static fn ($item): string => $item->title, $queue));
    }

    /** Vertretung (Feature 202): die Aufträge der vertretenen Person stehen in derselben Abarbeitungsreihenfolge, gekennzeichnet. */
    public function test_deputy_sees_the_represented_orders_in_working_order(): void {
        CarbonImmutable::setTestNow('2026-11-12 09:00:00');
        $deputy = User::factory()->user()->create(['organization_id' => $this->worker->organization_id, 'name' => 'Vertretung Beispiel']);
        app(\App\Services\Delegation\WorkDelegationService::class)->create(
            $this->worker,
            $deputy,
            CarbonImmutable::parse('2026-11-12'),
            CarbonImmutable::parse('2026-11-20'),
            \App\Enums\Delegation\WorkDelegationKind::Vacation,
            null,
            null,
            $this->worker,
        );
        foreach ([Status::Accepted, Status::ReviewQueried, Status::InProgress] as $status) {
            DiaryEntry::factory()->for($this->worker)->create([
                'organization_id' => $this->worker->organization_id,
                'assigned_user_id' => $this->worker->id,
                'status' => $status,
                'requires_review' => true,
                'due_date' => '2099-12-31',
                'title' => $status->name,
            ]);
        }

        $queue = array_values(array_filter(
            app(WorkQueue::class)->itemsFor($deputy, $this->organization),
            static fn ($item): bool => $item->source === 'assigned_entry',
        ));
        CarbonImmutable::setTestNow();

        $this->assertSame(['ReviewQueried', 'InProgress', 'Accepted'], array_map(static fn ($item): string => $item->title, $queue));
        $this->assertSame([$this->worker->name], array_values(array_unique(array_map(static fn ($item): ?string => $item->onBehalfOf, $queue))));
    }

    public function test_widget_lists_submissions_for_reviewers(): void {
        $entry = $this->submitted('2030-01-10', attributes: ['title' => 'Jahresabschluss Musterfirma']);

        $view = (new OrderReviewWidget(app(ReviewQueue::class)))->render($this->reviewer);
        $html = $view instanceof View ? $view->render() : $view;
        $this->assertStringContainsString('Jahresabschluss Musterfirma', $html);
        $this->assertTrue((new OrderReviewWidget(app(ReviewQueue::class)))->availableFor($this->reviewer));
        $this->assertSame(1, app(ReviewQueue::class)->count($this->reviewer, (int) $entry->organization_id));
    }

    public function test_demo_block_seeds_a_reviewed_period_order_with_a_note_and_purges_it(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->worker->organization_id]);
        $context = new DemoSeedContext($this->organization, $this->reviewer, collect([$this->reviewer, $this->worker]), [], null, null, null, $customer);
        $block = app(PeriodOrderDemoBlock::class);

        $this->assertSame(['period_orders' => 2, 'carry_forward_notes' => 1], $block->seed($context));
        $type = EntryType::query()->where('slug', PeriodOrderDemoBlock::ENTRY_TYPE_SLUG)->firstOrFail();
        [$previous, $current] = DiaryEntry::query()->where('entry_type_id', $type->id)->orderBy('id')->get()->all();
        $this->assertSame([Status::Completed, Status::InReview], [$previous->status, $current->status]);
        $this->assertSame($previous->period_key?->next()->key(), $current->period_key?->key());
        $this->assertCount(1, app(\App\Services\CarryForward\CarryForwardNotes::class)->forOccurrence($current)['open']);

        $block->purge($this->organization);
        $this->assertSame(0, DiaryEntry::query()->where('entry_type_id', $type->id)->count());
        $this->assertSame(0, CarryForwardNote::query()->count());
        $this->assertFalse(EntryType::query()->whereKey($type->id)->exists());
    }
}
