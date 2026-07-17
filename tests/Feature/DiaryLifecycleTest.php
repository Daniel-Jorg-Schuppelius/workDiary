<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\Status;
use App\Enums\Protocol\ProtocolStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\{DiaryEntry, DiaryEntryEvent, Project, Protocol, TimeEntry, User};
use App\Services\Diary\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DiaryLifecycleTest extends TestCase {
    use RefreshDatabase;

    public function test_service_executes_complete_lifecycle_and_records_events(): void {
        CarbonImmutable::setTestNow('2030-04-15 08:00:00');
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => Status::Planned,
        ]);
        $orders = app(OrderService::class);

        $entry = $orders->accept($entry, $user);
        $this->assertSame(Status::Accepted, $entry->status);
        $this->assertNotNull($entry->accepted_at);

        $entry = $orders->start($entry, $user);
        $this->assertSame(Status::InProgress, $entry->status);

        CarbonImmutable::setTestNow('2030-04-15 09:00:00');
        $entry = $orders->pause($entry, $user, 'customer', 'Freigabe fehlt.');
        $this->assertSame(Status::WaitingCustomer, $entry->status);

        CarbonImmutable::setTestNow('2030-04-15 09:30:00');
        $entry = $orders->resume($entry, $user);
        $this->assertSame(Status::InProgress, $entry->status);
        $this->assertSame(1800, $entry->wait_seconds_total);

        $entry = $orders->complete($entry, $user, 'Arbeiten dokumentiert.');
        $this->assertSame(Status::Completed, $entry->status);
        $this->assertSame('Arbeiten dokumentiert.', $entry->completion_summary);

        $protocol = Protocol::factory()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'status' => ProtocolStatus::Signed,
            'signed_at' => CarbonImmutable::now(),
            'created_by_user_id' => $user->id,
        ]);
        $entry = $orders->handover($entry, $user, $protocol);
        $this->assertSame(Status::AcceptedFinal, $entry->status);

        $entry = $orders->markInvoiced($entry, $user, 'RE-2030-0042');
        $this->assertSame(Status::Invoiced, $entry->status);
        $this->assertSame('RE-2030-0042', $entry->invoice_reference);

        $this->assertSame(
            ['order.created', 'order.accept', 'order.start', 'order.pause', 'order.resume', 'order.complete', 'order.handover', 'order.markInvoiced'],
            $entry->lifecycleEvents()->pluck('event')->all(),
        );
    }

    public function test_invalid_transition_is_rejected_without_event(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => Status::Planned,
        ]);

        $eventsBefore = $entry->lifecycleEvents()->count();
        $this->expectException(InvalidOrderTransitionException::class);
        try {
            app(OrderService::class)->complete($entry, $user, 'Zu früh.');
        } finally {
            $this->assertSame($eventsBefore, $entry->lifecycleEvents()->count());
        }
    }

    public function test_lifecycle_events_are_immutable(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => Status::Planned,
        ]);
        app(OrderService::class)->accept($entry, $user);
        $event = DiaryEntryEvent::query()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $event->update(['note' => 'Manipuliert']);
    }

    public function test_first_time_entry_starts_a_planned_order_automatically(): void {
        $user = User::factory()->user()->create();
        $project = Project::factory()->create(['organization_id' => $user->organization_id]);
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'project_id' => $project->id,
            'status' => Status::Planned,
        ]);

        TimeEntry::factory()->create([
            'organization_id' => $user->organization_id,
            'project_id' => $project->id,
            'diary_entry_id' => $entry->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(Status::InProgress, $entry->refresh()->status);
        $this->assertSame(
            ['order.created', 'order.accept', 'order.start'],
            $entry->lifecycleEvents()->pluck('event')->all(),
        );
    }

    public function test_owner_can_accept_order_via_web_action(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => Status::Planned,
        ]);

        $this->actingAs($user)
            ->post(route('diary.lifecycle', ['diary' => $entry, 'action' => 'accept']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Status::Accepted, $entry->refresh()->status);
    }
}
