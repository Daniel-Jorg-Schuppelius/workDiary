<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderReviewLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Enums\Diary\Status;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Audit\AuditLog;
use App\Models\Classification\EntryType;
use App\Models\Diary\{DiaryEntry, DiaryEntryEvent};
use App\Models\Platform\User;
use App\Services\Diary\OrderService;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Interne Prüfung im Lebenszyklus (Feature 198, MVP-1635): drei Prüfzustände
 * nur bei Prüfpflicht, nie durch die vorlegende Person, Abschluss erst nach
 * der Freigabe. Regressionstest: ohne Prüfpflicht bleibt der Lebenszyklus
 * exakt wie bisher.
 */
final class OrderReviewLifecycleTest extends TestCase {
    use RefreshDatabase;

    private User $worker;

    private User $reviewer;

    private OrderService $orders;

    protected function setUp(): void {
        parent::setUp();
        $this->worker = User::factory()->user()->create();
        $this->reviewer = User::factory()->teamleitung()->create(['organization_id' => $this->worker->organization_id]);
        $this->orders = app(OrderService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function order(Status $status, bool $requiresReview = true, array $attributes = []): DiaryEntry {
        return DiaryEntry::factory()->for($this->worker)->create(array_merge([
            'organization_id' => $this->worker->organization_id,
            'assigned_user_id' => $this->worker->id,
            'status' => $status,
            'requires_review' => $requiresReview,
        ], $attributes));
    }

    public function test_status_values_are_new_and_do_not_collide(): void {
        $this->assertSame(9, Status::InReview->value);
        $this->assertSame(10, Status::ReviewQueried->value);
        $this->assertSame(11, Status::ReviewApproved->value);
        // Bestandswerte bleiben unverändert.
        $this->assertSame([-1, 1, 2, 3, 4, 5, 6, 7, 8], array_slice(array_map(static fn (Status $s): int => $s->value, Status::cases()), 0, 9));
        $this->assertSame(['progress', 'alert', 'progress'], [Status::InReview->stage(), Status::ReviewQueried->stage(), Status::ReviewApproved->stage()]);
        $this->assertSame(['info', 'error', 'info'], [Status::InReview->tone(), Status::ReviewQueried->tone(), Status::ReviewApproved->tone()]);
        $this->assertSame('Zur Prüfung', Status::InReview->label());
        // Im Kundenportal ist die interne Prüfung kein eigener Schritt.
        $this->assertSame(Status::InProgress->label(), Status::ReviewQueried->customerLabel());
        $this->assertSame(Status::Completed->label(), Status::Completed->customerLabel());
    }

    /** Regressionstest (Plan Phase 177, Risiken): ohne Prüfpflicht exakt der bisherige Ablauf. */
    public function test_lifecycle_without_review_requirement_is_unchanged(): void {
        $expected = [
            Status::Open->value => ['accept', 'cancel'],
            Status::Accepted->value => ['start', 'reject', 'cancel'],
            Status::InProgress->value => ['pause', 'complete', 'cancel'],
            Status::Problem->value => ['resume'],
            Status::WaitingMaterial->value => ['resume'],
            Status::Done->value => ['handover'],
            Status::AcceptedFinal->value => ['markInvoiced'],
            Status::Invoiced->value => ['revertInvoiced'],
            Status::Cancelled->value => [],
        ];
        foreach ($expected as $value => $actions) {
            $this->assertSame($actions, Status::from($value)->allowedActions(), 'Status ' . $value);
            $this->assertSame($actions, Status::from($value)->allowedActions(false), 'Status ' . $value);
        }

        $entry = $this->order(Status::Planned, false);
        $entry = $this->orders->accept($entry, $this->worker);
        $entry = $this->orders->start($entry, $this->worker);
        $entry = $this->orders->complete($entry, $this->worker, 'Fertig wie bisher.');
        $this->assertSame(Status::Completed, $entry->status);

        $events = DiaryEntryEvent::query()->where('diary_entry_id', $entry->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['order.created', 'order.accept', 'order.start', 'order.complete'], $events);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->orders->submitForReview($this->order(Status::InProgress, false), $this->worker);
    }

    public function test_review_required_order_cannot_be_completed_from_in_progress(): void {
        $entry = $this->order(Status::InProgress);
        $this->assertSame(['pause', 'submitReview', 'cancel'], $entry->lifecycleActions());

        $this->expectException(InvalidOrderTransitionException::class);
        $this->orders->complete($entry, $this->worker, 'Zu früh.');
    }

    public function test_full_review_cycle_with_query_resubmit_and_approval(): void {
        $entry = $this->order(Status::InProgress);

        $entry = $this->orders->submitForReview($entry, $this->worker, 'Bitte prüfen.');
        $this->assertSame(Status::InReview, $entry->status);
        $this->assertSame($this->worker->id, $entry->review_submitter_user_id);
        $this->assertNotNull($entry->review_submitted_at);

        $entry = $this->orders->queryReview($entry, $this->reviewer, 'Fehlt der Nachweis für März?');
        $this->assertSame(Status::ReviewQueried, $entry->status);
        $this->assertSame('Fehlt der Nachweis für März?', DiaryEntryEvent::query()->where('diary_entry_id', $entry->id)->where('event', 'order.queryReview')->value('note'));

        $entry = $this->orders->resubmitForReview($entry, $this->worker);
        $this->assertSame(Status::InReview, $entry->status);

        $entry = $this->orders->approveReview($entry, $this->reviewer);
        $this->assertSame(Status::ReviewApproved, $entry->status);
        $this->assertSame(['complete', 'cancel'], $entry->lifecycleActions());

        $entry = $this->orders->complete($entry, $this->worker, 'Übermittelt.');
        $this->assertSame(Status::Completed, $entry->status);
    }

    public function test_the_submitter_cannot_approve_or_query_the_own_submission(): void {
        $entry = $this->orders->submitForReview($this->order(Status::InProgress), $this->reviewer);
        $this->assertFalse($this->reviewer->can('approveReview', $entry));

        try {
            $this->orders->approveReview($entry, $this->reviewer);
            $this->fail('Eigene Vorlage freigegeben.');
        } catch (InvalidOrderTransitionException) {
            $this->assertSame(Status::InReview, $entry->refresh()->status);
        }

        $this->expectException(InvalidOrderTransitionException::class);
        $this->orders->queryReview($entry, $this->reviewer, 'Selbst gefragt.');
    }

    public function test_a_query_without_text_is_rejected(): void {
        $entry = $this->orders->submitForReview($this->order(Status::InProgress), $this->worker);

        $this->expectException(InvalidArgumentException::class);
        $this->orders->queryReview($entry, $this->reviewer, "  \n ");
    }

    public function test_withdraw_returns_to_in_progress_and_approve_and_complete_closes_in_one_step(): void {
        $entry = $this->orders->submitForReview($this->order(Status::InProgress), $this->worker);
        $entry = $this->orders->withdrawReview($entry, $this->worker);
        $this->assertSame(Status::InProgress, $entry->status);

        $entry = $this->orders->submitForReview($entry, $this->worker);
        $entry = $this->orders->approveAndComplete($entry, $this->reviewer, 'Freigabe ist der Abschluss.');
        $this->assertSame(Status::Completed, $entry->status);
        $this->assertSame($this->reviewer->id, $entry->completed_by_user_id);
    }

    public function test_review_right_or_named_reviewer_may_review(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->worker->organization_id]);
        $named = User::factory()->user()->create(['organization_id' => $this->worker->organization_id]);
        $entry = $this->orders->submitForReview($this->order(Status::InProgress, attributes: ['reviewer_user_id' => $named->id]), $this->worker);

        $this->assertTrue($this->reviewer->can('approveReview', $entry));
        $this->assertTrue($named->can('approveReview', $entry));
        $this->assertTrue($named->can('view', $entry));
        $this->assertFalse($colleague->can('approveReview', $entry));
        $this->assertFalse($this->worker->can('approveReview', $entry));
    }

    public function test_lifecycle_route_runs_the_review_actions(): void {
        $entry = $this->order(Status::InProgress);

        $this->actingAs($this->worker)->post(route('diary.lifecycle', [$entry, 'action' => 'submitReview']))->assertRedirect();
        $this->assertSame(Status::InReview, $entry->refresh()->status);

        $this->actingAs($this->reviewer)->post(route('diary.lifecycle', [$entry, 'action' => 'queryReview']), ['question' => ''])->assertSessionHasErrors('question');
        $this->actingAs($this->reviewer)->post(route('diary.lifecycle', [$entry, 'action' => 'queryReview']), ['question' => 'Bitte Beleg ergänzen.'])->assertRedirect();
        $this->assertSame(Status::ReviewQueried, $entry->refresh()->status);

        $this->actingAs($this->worker)->post(route('diary.lifecycle', [$entry, 'action' => 'approveReview']))->assertForbidden();
        $this->actingAs($this->worker)->get(route('diary.show', $entry))->assertOk()->assertSee(__('diary.lifecycle.action.resubmit_review'));
    }

    public function test_review_requirement_follows_the_entry_type_and_can_be_waived_with_the_right(): void {
        $type = EntryType::factory()->create(['organization_id' => $this->worker->organization_id, 'requires_review' => true]);
        $entry = DiaryEntry::factory()->for($this->worker)->create([
            'organization_id' => $this->worker->organization_id,
            'entry_type_id' => $type->id,
            'status' => Status::InProgress,
        ]);
        $this->assertTrue($entry->refresh()->requires_review);

        $this->actingAs($this->worker)->patch(route('diary.review-requirement', $entry), ['requires_review' => 0])->assertForbidden();

        $this->actingAs($this->reviewer)->patch(route('diary.review-requirement', $entry), ['requires_review' => 0, 'reason' => 'Eilfall ohne Prüfung.'])->assertRedirect();
        $entry->refresh();
        $this->assertFalse($entry->requires_review);
        $this->assertSame('Eilfall ohne Prüfung.', DiaryEntryEvent::query()->where('diary_entry_id', $entry->id)->where('event', 'order.reviewWaived')->value('note'));
        $this->assertTrue(AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(DiaryEntry::class))
            ->where('auditable_id', $entry->id)
            ->get()
            ->contains(static fn (AuditLog $log): bool => array_key_exists('requires_review', (array) $log->changes)));
        $this->assertSame(['pause', 'complete', 'cancel'], $entry->lifecycleActions());
    }

    /**
     * Fertige Arbeit außerhalb des Auftragsdialogs (Fahrt, Rundgang, Tourstopp):
     * ein geplanter Auftrag mit Prüfpflicht läuft über Annehmen und Beginnen in
     * die Vorlage; ein erledigter oder schon vorgelegter bleibt, wie er ist.
     */
    public function test_finished_work_is_submitted_through_the_lifecycle(): void {
        $planned = $this->orders->submitFinishedWork($this->order(Status::Planned), $this->worker, 'Fahrt beendet.');
        $this->assertSame(Status::InReview, $planned->status);
        $this->assertSame($this->worker->id, (int) $planned->review_submitter_user_id);
        $this->assertSame(
            ['order.created', 'order.accept', 'order.start', 'order.submitReview'],
            DiaryEntryEvent::query()->where('diary_entry_id', $planned->id)->orderBy('id')->pluck('event')->all(),
        );

        $done = $this->order(Status::Done);
        $this->assertSame(Status::Done, $this->orders->submitFinishedWork($done, $this->worker)->status);
        $inReview = $this->orders->submitForReview($this->order(Status::InProgress), $this->worker);
        $this->assertSame(Status::InReview, $this->orders->submitFinishedWork($inReview, $this->reviewer)->status);
        $this->assertSame($this->worker->id, (int) $inReview->refresh()->review_submitter_user_id);
    }

    public function test_review_requirement_is_locked_during_review(): void {
        $entry = $this->orders->submitForReview($this->order(Status::InProgress), $this->worker);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->orders->setReviewRequirement($entry, $this->reviewer, false, 'zu spät');
    }
}
